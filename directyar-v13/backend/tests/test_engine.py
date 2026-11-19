import json
import pathlib
import sys
import unittest
sys.path.insert(0,str(pathlib.Path(__file__).resolve().parents[1]))
from engine import (Repository, RuleEngine, validate_rules, normalize, parse_webhook, PRIVATE_PROMPT, DEFAULT_COMMENT)

BUSINESS = "17841400000000001"
USER = "12345678890"
RULES = [dict(id="training", keyword="آموزش", reply="لینک آموزش: https://example.org/tutorial",
              post_id="", dm_enabled=True, comment_enabled=True, follow_required=True,
              comment_public_reply="دایرکت براتون ارسال شد")]

class FakeMeta:
    def __init__(self, follow):
        self.follow = follow
        self.calls = []
        self.fail_private = False
    def follow_status(self, user):
        self.calls.append(("status",user));return self.follow
    def dm(self, user, text, quick=None):
        self.calls.append(("dm",user,text,quick));return {"message_id": "m1"}
    def private_reply(self, comment, text, quick=None):
        self.calls.append(("private",comment,text,quick))
        if self.fail_private: raise RuntimeError("private failed")
        return {"message_id":"private1"}
    def public_reply(self, comment, text):
        self.calls.append(("public",comment,text));return {"id": "public1"}

class TestFlows(unittest.TestCase):
    def setUp(self):
        self.repo = Repository(":memory:")
        self.repo.put_rules(RULES)
        self.meta=FakeMeta(False)
        self.engine=RuleEngine(self.repo,self.meta,BUSINESS,live=True)
    def dm(self,mid,txt="آموزش",payload=""):
        return {"business_id":BUSINESS,"type":"dm","user_id":USER,"message_id":mid,"text":txt,"payload":payload}
    def test_unicode_match(self):
        self.assertEqual(normalize("    اموزش   " ),"اموزش")
        self.assertEqual(normalize("يک  كلمه"),"یک کلمه")
    def test_requires_follow_and_repeat_before_content(self):
        self.assertEqual(self.engine.process_dm(self.dm("A")),"follow_requested")
        self.assertEqual(self.meta.calls[-1][3],"FOLLOW_CHECK:training")
        self.assertEqual(self.engine.process_dm(self.dm("B","فالو کردم","FOLLOW_CHECK:training")),"follow_requested")
        self.meta.follow=True
        self.assertEqual(self.engine.process_dm(self.dm("C","فالو کردم","FOLLOW_CHECK:training")),"delivered")
        self.assertIn("https://example.org/tutorial",self.meta.calls[-1][2])
        self.assertIsNone(self.repo.get_pending(USER))
        self.assertEqual(self.engine.process_dm(self.dm("C","فالو کردم")),"no_rule")
    def test_follower_gets_reply(self):
        self.meta.follow=True
        self.assertEqual(self.engine.process_dm(self.dm("A")),"delivered")
        self.assertEqual(self.meta.calls[-1][0],"dm")
    def test_duplicates_not_sent(self):
        self.engine.process_dm(self.dm("A")); self.engine.process_dm(self.dm("A"))
        self.assertEqual(len(self.meta.calls),2)
    def test_unverified_never_sends_content(self):
        self.meta.follow=None
        self.assertEqual(self.engine.process_dm(self.dm("A")),"unverified")
        self.assertEqual([a[0] for a in self.meta.calls],["status", "dm"])
        self.assertNotIn("https://example.org/tutorial", self.meta.calls[-1][2])
    def test_wrong_account(self):
        event=self.dm("A");event["business_id"]="OTHER"
        self.assertEqual(self.engine.process_dm(event),"ignored_account")
        self.assertEqual(self.meta.calls,[])
    def test_button_requires_pending(self):
        self.assertEqual(self.engine.process_dm(self.dm("A","","FOLLOW_CHECK:training")),"no_rule")
        self.assertEqual(self.meta.calls,[])
    def test_comment_sends_private_then_public(self):
        event={"type":"comment","business_id":BUSINESS,"comment_id":"C1","media_id":"M1","text":"آموزش", "user_id":USER}
        self.assertEqual(self.engine.process_comment(event),"completed")
        self.assertEqual([c[0] for c in self.meta.calls],["private","public"])
        self.assertEqual(self.meta.calls[0][3], "FOLLOW_CHECK:training")
        self.assertIn("فالو کردم", self.meta.calls[0][2])
        self.assertEqual(self.repo.get_pending(USER),"training")
        self.assertEqual(self.engine.process_comment(event),"duplicate")
        self.assertEqual(self.meta.calls[0][0],"private")
    def test_comment_failure_never_claims_sent(self):
        self.meta.fail_private=True
        event={"type":"comment","business_id":BUSINESS,"comment_id":"C1","media_id":"M1","text":"آموزش", "user_id":USER}
        self.assertEqual(self.engine.process_comment(event),"error")
        self.assertEqual([c[0] for c in self.meta.calls],["private"])
    def test_comment_follow_loop_until_confirmed(self):
        event={"type":"comment","business_id":BUSINESS,"comment_id":"COM3","media_id":"M1","text":"آموزش", "user_id":USER}
        self.assertEqual(self.engine.process_comment(event), "completed")
        self.assertEqual(self.engine.process_dm(self.dm("CLICK1","فالو کردم","FOLLOW_CHECK:training")), "follow_requested")
        self.assertEqual(self.engine.process_dm(self.dm("CLICK2","فالو کردم","FOLLOW_CHECK:training")), "follow_requested")
        self.assertEqual(self.meta.calls[-1][-1], "FOLLOW_CHECK:training")
        self.meta.follow=True
        self.assertEqual(self.engine.process_dm(self.dm("CLICK3","فالو کردم","FOLLOW_CHECK:training")), "delivered")
        self.assertIsNone(self.repo.get_pending(USER))
        self.assertEqual(self.meta.calls[-1][2], RULES[0]["reply"])
        self.assertEqual(self.engine.process_dm(self.dm("CLICK4","فالو کردم","FOLLOW_CHECK:training")), "no_rule")

    def test_comment_without_user_fails_closed(self):
        e={"type":"comment","business_id":BUSINESS,"comment_id":"X","media_id":"Y","text":"آموزش"}
        self.assertEqual(self.engine.process_comment(e), "ignored_missing_id")

    def test_comment_dry_run_has_no_side_effect(self):
        self.engine.live=False
        e={"type":"comment","business_id":BUSINESS,"comment_id":"C4","media_id":"M1","text":"آموزش", "user_id":USER}
        self.assertEqual(self.engine.process_comment(e), "simulated")
        self.assertEqual(self.meta.calls, [])

    def test_follow_daram_text_from_user_rechecks(self):
        e={"type":"comment","business_id":BUSINESS,"comment_id":"C6","media_id":"M1","text":"آموزش", "user_id":USER}
        self.engine.process_comment(e)
        self.assertEqual(self.engine.process_dm(self.dm("USERFOLLOW", "فالو دارم")), "follow_requested")
        self.meta.follow=True
        self.assertEqual(self.engine.process_dm(self.dm("USERFOLLOW2", "فالو دارم")), "delivered")

    def test_comment_initial_does_not_require_keyword_again(self):
        e={"type":"comment","business_id":BUSINESS,"comment_id":"C5","media_id":"M1","text":"آموزش لطفا", "user_id":USER}
        self.engine.process_comment(e)
        self.meta.follow=True
        self.assertEqual(self.engine.process_dm(self.dm("CLICK3","فالو کردم","FOLLOW_CHECK:training")), "delivered")

    def test_dry_run_no_api_call(self):
        self.engine.live=False
        self.assertEqual(self.engine.process_dm(self.dm("A")),"simulated")
        self.assertEqual(self.meta.calls,[])
    def test_webhook_parsing(self):
        data={"object":"instagram","entry":[{"id":BUSINESS,"changes":[{"field":"comments","value":{"id":"C","text":"آموزش","media":{"id":"M"},"from":{"id":USER}}}],"messaging":[{"sender":{"id":USER},"timestamp":100,"message":{"mid":"MM","text":"آموزش","quick_reply":{"payload":"FOLLOW_CHECK:training"}}}]}]}
        out=parse_webhook(data)
        self.assertEqual([e["type"] for e in out],["comment","dm"])
        self.assertEqual(out[1]["payload"],"FOLLOW_CHECK:training")
    def test_validation(self):
        with self.assertRaises(ValueError):validate_rules([dict(RULES[0],reply="")])
        with self.assertRaises(ValueError):validate_rules([RULES[0],RULES[0]])

if __name__=="__main__": unittest.main()
