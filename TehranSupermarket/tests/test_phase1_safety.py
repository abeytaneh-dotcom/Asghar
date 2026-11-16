#!/usr/bin/env python3
"""Phase 1 static safety regressions. No DB, network or production access."""
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[1]


def load(name: str) -> str:
    return (ROOT / name).read_text(encoding="utf-8")


class Phase1Safety(unittest.TestCase):
    def test_no_implicit_schema_or_backup_on_http_pages(self):
        forbidden = (
            r"ensure_product_schema\(\$pdo\)\s*;",
            r"ts_admin_schema\(\$pdo\)\s*;",
            r"ts_coupon_schema\(\$pdo\)\s*;",
            r"ts_ai_learning_schema\(\$pdo\)\s*;",
            r"ts_sync_ensure_schema\(\$pdo\)\s*;",
            r"ts_schedule_auto_backup\(\$pdo\)\s*;",
        )
        for page in ROOT.glob("*.php"):
            if page.name in {"schema.php", "admin_auth.php", "coupon_lib.php",
                             "ai_learning_lib.php", "pyramid_sync_lib.php"}:
                continue  # CLI-only helper definitions, never called on HTTP
            content = page.read_text(encoding="utf-8")
            for pattern in forbidden:
                with self.subTest(page=page.name, pattern=pattern):
                    self.assertIsNone(re.search(pattern, content))

    def test_index_never_seeds_or_mutates_catalogue_on_get(self):
        content = load("index.php")
        self.assertNotIn("ts_seed_pyramid_1400();", content)
        self.assertNotIn("ts_apply_product_image_manifest($pdo);", content)

    def test_retired_installer_and_demo_seed_are_inert(self):
        installer, seed = load("install.php"), load("pyramid_seed_1400.php")
        self.assertIn("http_response_code(403)", installer)
        self.assertNotIn("CREATE TABLE", installer)
        self.assertIn("http_response_code(403)", seed)
        self.assertNotIn("UPDATE products", seed)
        self.assertNotIn("stock=10", seed)

    def test_no_auto_generated_barcode_or_demo_stock_migration(self):
        schema = load("schema.php")
        self.assertNotIn("pyramid_internal_ean13", schema)
        self.assertNotIn("ensure_pyramid_mapping($pdo);", schema)
        self.assertNotIn("$stock = 10", schema)

    def test_pyramid_writes_are_denied_before_action_handlers(self):
        api = load("pyramid_api.php")
        gate = api.index("'error'=>'sync_read_only'")
        for action in ("product_update", "products_batch", "order_ack"):
            self.assertIn(action, api[:gate])
            self.assertGreater(api.index("if($action==='" + action + "'"), gate)
        self.assertNotIn("ts_sync_ensure_schema($pdo);", api)

    def test_sync_key_loading_never_creates_one(self):
        helper = load("pyramid_sync_lib.php")
        body = helper.split("function ts_sync_key(): string {", 1)[1].split(
            "function ts_sync_ensure_schema", 1
        )[0]
        self.assertIn("is_file", body)
        self.assertNotIn("file_put_contents", body)
        self.assertNotIn("mkdir", body)

    def test_staging_probe_is_cli_readonly_and_explicit(self):
        probe = load("bin/preflight.php")
        self.assertIn("PHP_SAPI !== 'cli'", probe)
        self.assertIn("TEHRAN_STAGING", probe)
        self.assertIn("TRANSACTION READ ONLY", probe)
        self.assertNotIn("UPDATE products", probe)
        self.assertNotIn("ALTER TABLE", probe)

    def test_sensitive_files_and_upload_executables_blocked(self):
        htaccess = load(".htaccess")
        for target in ("install", "schema", "pyramid_seed_1400",
                       "product_image_seed", "pyramid_sync_lib"):
            self.assertIn(target, htaccess)
        self.assertIn("Require all denied", htaccess)
        self.assertIn("RewriteRule ^(?:storage", htaccess)
        upload = load("uploads/.htaccess")
        self.assertIn("Require all denied", upload)
        self.assertIn("php", upload)


if __name__ == "__main__":
    unittest.main(verbosity=2)
