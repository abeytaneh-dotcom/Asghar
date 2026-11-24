#pragma once
#include <stdint.h>
#include <stddef.h>
#include <string.h>

namespace kr {
enum class IsoResult { Ignored, More, Complete, Invalid };
class IsoTpRx {
 public:
  IsoTpRx(uint8_t* out, size_t capacity, uint8_t sid)
    : out_(out), cap_(capacity), sid_(sid) {}
  IsoResult feed(const uint8_t* d, size_t dlc) {
    if (!d || dlc < 2 || dlc > 8 || failed_) return fail();
    uint8_t type = d[0] >> 4;
    if (!active_) {
      if (type == 0) {
        size_t n = d[0] & 15;
        if (!n || n > 7 || n + 1 > dlc || n > cap_) return fail();
        if (d[1] != sid_) return IsoResult::Ignored;
        memcpy(out_, d + 1, n); used_ = total_ = n;
        return IsoResult::Complete;
      }
      if (type != 1) return IsoResult::Ignored;
      if (dlc != 8) return fail();
      size_t n = ((size_t)(d[0] & 15) << 8) | d[1];
      if (n <= 7 || n > cap_) return fail();
      if (d[2] != sid_) return IsoResult::Ignored;
      total_ = n; used_ = 6; memcpy(out_, d + 2, used_);
      active_ = true; seq_ = 1; return IsoResult::More;
    }
    if (type != 2 || (d[0] & 15) != (seq_ & 15)) return fail();
    size_t remaining = total_ - used_;
    size_t take = remaining < 7 ? remaining : 7;
    if (dlc < take + 1) return fail();
    memcpy(out_ + used_, d + 1, take); used_ += take; ++seq_;
    return used_ == total_ ? IsoResult::Complete : IsoResult::More;
  }
  size_t size() const { return failed_ ? 0 : used_; }
 private:
  IsoResult fail() { failed_ = true; used_ = 0; return IsoResult::Invalid; }
  uint8_t* out_; size_t cap_, used_ = 0, total_ = 0;
  uint8_t sid_, seq_ = 1; bool active_ = false, failed_ = false;
};

inline uint8_t checksum(const uint8_t* d, size_t n) {
  uint8_t sum = 0; for (size_t i = 0; i < n; ++i) sum += d[i]; return sum;
}
// Accept only a complete, checksummed response addressed to this tester.
inline bool klineFrame(const uint8_t* frame, size_t n, bool kwp,
                       uint8_t ecu, uint8_t* out, size_t cap, size_t& used) {
  used = 0;
  if (!frame || n < 5) return false;
  size_t payloadLen = n - 4;
  if (kwp) {
    if ((frame[0] & 0xC0) != 0x80 || (frame[0] & 63) != payloadLen ||
        frame[1] != 0xF1 || frame[2] != ecu) return false;
  } else if (frame[0] != 0x48 || frame[1] != 0x6B || frame[2] != ecu) {
    return false;
  }
  if (payloadLen > cap || checksum(frame, n - 1) != frame[n - 1]) return false;
  memcpy(out, frame + 3, payloadLen); used = payloadLen; return true;
}
inline uint8_t saturatingIncrement(uint8_t n) { return n == 255 ? n : n + 1; }
inline bool dtcLayout(const uint8_t* d,size_t n,bool can,size_t& offset,size_t& count){
  offset=can?2:1;count=0;
  if(n<offset || d[0]!=0x43)return false;
  if(can){
    count=d[1];
    if(count>(n-offset)/2)return false;
    // Some ECUs pad a service response with zeros.
    for(size_t i=offset+count*2;i<n;i++)if(d[i])return false;
  }else{
    if((n-offset)%2)return false;
    count=(n-offset)/2;
  }
  return true;
}
inline bool freshStopped(float rpm, bool valid, uint32_t age) {
  return valid && age <= 1000 && rpm >= 0 && rpm <= 50;
}
enum class FragmentResult { More, Complete, Invalid };
template<size_t Capacity> class CommandFragments {
 public:
  void clear() { used_=0;next_=total_=0;id_=-1;buffer_[0]=0; }
  bool expired(uint32_t now, uint32_t timeout) const {
    return total_ && uint32_t(now-last_)>timeout;
  }
  FragmentResult feed(const char* text,size_t len,uint32_t now) {
    if(len<12 || text[0]!='Y'||text[1]!='|'||text[4]!='|'||
       text[7]!='|'||text[10]!='|')return invalid();
    int id=hex(text+2),seq=hex(text+5),total=hex(text+8);
    if(id<0||seq<0||total<1||seq>=total)return invalid();
    if(seq==0){clear();id_=id;total_=total;}
    if(id!=id_||seq!=next_||total!=total_)return invalid();
    size_t n=len-11;
    if(n>Capacity-used_ || memchr(text+11,0,n))return invalid();
    memcpy(buffer_+used_,text+11,n);used_+=n;buffer_[used_]=0;last_=now;
    return ++next_==total_ ? FragmentResult::Complete : FragmentResult::More;
  }
  const char* value()const{return buffer_;}
 private:
  FragmentResult invalid(){clear();return FragmentResult::Invalid;}
  static int digit(char c){
    return c>='0'&&c<='9'?c-'0':c>='A'&&c<='F'?c-'A'+10:c>='a'&&c<='f'?c-'a'+10:-1;
  }
  static int hex(const char* s){int a=digit(s[0]),b=digit(s[1]);return a<0||b<0?-1:(a<<4)|b;}
  char buffer_[Capacity+1]={};size_t used_=0;
  int id_=-1,next_=0,total_=0;uint32_t last_=0;
};
}
