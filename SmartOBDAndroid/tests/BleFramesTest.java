package ir.khanehremap.smartobd;
import java.nio.charset.StandardCharsets;
import java.util.List;
public class BleFramesTest {
    static void check(boolean value){if(!value)throw new AssertionError();}
    static byte[] ascii(String s){return s.getBytes(StandardCharsets.UTF_8);}
    public static void main(String[] args){
        BleFrames rx=new BleFrames();
        check(rx.accept(ascii("S|KR-ESP32-12345678"),0).equals("S|KR-ESP32-12345678"));
        check(rx.accept(ascii("X|01|00|02|ID|ECU|"),0)==null);
        check(rx.accept(ascii("X|01|01|02|J34"),10).equals("ID|ECU|J34"));
        check(rx.accept(ascii("X|02|01|02|bad"),20)==null);
        check(rx.accept(ascii("X|02|00|00|bad"),20)==null);
        check(rx.accept(ascii("X|GG|00|01|bad"),20)==null);
        check(rx.accept(ascii("X|03|00|02|first"),30)==null);
        check(rx.accept(ascii("X|03|01|02|last"),2031)==null);
        String command="E|پژو ۲۰۶|J34|خانه ریمپ";
        List<byte[]> packets=BleFrames.command(command,255);
        String result=null;
        for(byte[] p:packets){
            check(p.length<=20);p[0]='X';result=rx.accept(p,3000);
        }
        check(command.equals(result));
        check(BleFrames.command("PING",0).get(0).length==4);
        check(BleFrames.isTelemetry("T|0|0|-|-|12.0|0|1|1|NONE|0|0".split("\\|",-1)));
        check(!BleFrames.isTelemetry("T|0|0|-|-|12.0|0|1|1|NONE|0".split("\\|",-1)));
        try{BleFrames.command(new String(new char[513]).replace('\0','A'),1);throw new AssertionError();}
        catch(IllegalArgumentException expected){}
        System.out.println("PASS: BLE framing, delimiter, UTF-8, sequence, timeout and command limits");
    }
}
