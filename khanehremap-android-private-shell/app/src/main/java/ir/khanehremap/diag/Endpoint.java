package ir.khanehremap.diag;

import android.util.Base64;
import java.nio.charset.StandardCharsets;

final class Endpoint {
    private Endpoint() {}

    private static final String[] P = {
            "aHR0cHM6Ly9h", "Y2hpbnUuaXI=", "L2RpYWcv"
    };

    static String launchUrl() {
        String a = new String(Base64.decode(P[0], Base64.DEFAULT), StandardCharsets.UTF_8);
        String b = new String(Base64.decode(P[1], Base64.DEFAULT), StandardCharsets.UTF_8);
        String c = new String(Base64.decode(P[2], Base64.DEFAULT), StandardCharsets.UTF_8);
        return a + b + c;
    }

    static String origin() {
        String u = launchUrl();
        int slash = u.indexOf('/', 8);
        return slash > 0 ? u.substring(0, slash) : u;
    }

    static String assetLinksUrl() {
        return origin() + "/.well-known/assetlinks.json";
    }
}
