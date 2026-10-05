package ir.khanehremap.smartobd;

import java.io.ByteArrayOutputStream;
import java.nio.charset.StandardCharsets;
import java.util.ArrayList;
import java.util.List;
import java.util.Locale;

/** Byte-oriented framing preserves UTF-8 across MTU-23 fragments. */
final class BleFrames {
    static final int MAX_RX_BYTES=2295, MAX_COMMAND_BYTES=512;
    private int rxId=-1, next=0, total=0;
    private long last=0;
    private final ByteArrayOutputStream incoming=new ByteArrayOutputStream();
    void reset(){rxId=-1;next=total=0;last=0;incoming.reset();}
    synchronized String accept(byte[] value,long now){
        if(value==null||value.length==0)return null;
        if(value[0]!='X'||value.length<2||value[1]!='|'){
            return new String(value,StandardCharsets.UTF_8);
        }
        if(total>0&&now-last>2000)reset();
        if(value.length<12||value[4]!='|'||value[7]!='|'||value[10]!='|'){reset();return null;}
        int id=hex(value,2),seq=hex(value,5),count=hex(value,8);
        if(id<0||seq<0||count<1||seq>=count){reset();return null;}
        if(seq==0){reset();rxId=id;total=count;}
        if(id!=rxId||seq!=next||count!=total||incoming.size()+value.length-11>MAX_RX_BYTES){
            reset();return null;
        }
        incoming.write(value,11,value.length-11);next++;last=now;
        if(next!=total)return null;
        String complete=new String(incoming.toByteArray(),StandardCharsets.UTF_8);
        reset();return complete;
    }
    static List<byte[]> command(String text,int id){
        byte[] bytes=text.getBytes(StandardCharsets.UTF_8);
        if(bytes.length==0||bytes.length>MAX_COMMAND_BYTES)throw new IllegalArgumentException("command size");
        List<byte[]> packets=new ArrayList<>();
        if(bytes.length<=20){packets.add(bytes);return packets;}
        int count=(bytes.length+8)/9;
        for(int seq=0;seq<count;seq++){
            byte[] header=String.format(Locale.US,"Y|%02X|%02X|%02X|",id&255,seq,count).getBytes(StandardCharsets.US_ASCII);
            int length=Math.min(9,bytes.length-seq*9);
            byte[] packet=new byte[header.length+length];
            System.arraycopy(header,0,packet,0,header.length);
            System.arraycopy(bytes,seq*9,packet,header.length,length);
            packets.add(packet);
        }
        return packets;
    }
    static boolean isTelemetry(String[] fields){
        return fields.length==12&&fields[0].equals("T")&&
                (fields[7].equals("0")||fields[7].equals("1"))&&
                (fields[8].equals("0")||fields[8].equals("1"));
    }
    private static int hex(byte[] data,int offset){
        int a=digit(data[offset]),b=digit(data[offset+1]);
        return a<0||b<0?-1:(a<<4)|b;
    }
    private static int digit(int c){
        return c>='0'&&c<='9'?c-'0':c>='A'&&c<='F'?c-'A'+10:c>='a'&&c<='f'?c-'a'+10:-1;
    }
}
