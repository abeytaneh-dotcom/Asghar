package ir.khanehremap.diag;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import javax.net.ssl.HttpsURLConnection;

final class Preflight {
    static final String PACKAGE = "ir.khanehremap.smartdiag";
    static final String FINGERPRINT = "E1:FF:41:3B:FA:A1:60:2E:A7:65:35:EA:F8:A1:6D:30:C3:04:CC:DE:80:E7:24:45:2B:61:CF:FC:BB:45:76:11";

    private Preflight() {}

    static int check() {
        try {
            int code = request(Endpoint.launchUrl());
            if (code < 200 || code >= 400) return 1;
            String json = read(Endpoint.assetLinksUrl());
            if (json == null || !json.contains(PACKAGE) || !json.contains(FINGERPRINT)) return 2;
            return 0;
        } catch (Exception e) {
            return 1;
        }
    }

    private static int request(String u) throws Exception {
        HttpsURLConnection c = (HttpsURLConnection) new URL(u).openConnection();
        c.setConnectTimeout(7000);
        c.setReadTimeout(7000);
        c.setInstanceFollowRedirects(true);
        c.setRequestProperty("User-Agent", "KhanehRemapApp/2.9.1");
        try {
            return c.getResponseCode();
        } finally {
            c.disconnect();
        }
    }

    private static String read(String u) throws Exception {
        HttpsURLConnection c = (HttpsURLConnection) new URL(u).openConnection();
        c.setConnectTimeout(7000);
        c.setReadTimeout(7000);
        c.setInstanceFollowRedirects(true);
        if (c.getResponseCode() != HttpURLConnection.HTTP_OK) {
            c.disconnect();
            return null;
        }
        StringBuilder b = new StringBuilder();
        try (BufferedReader r = new BufferedReader(new InputStreamReader(c.getInputStream()))) {
            String line;
            while ((line = r.readLine()) != null) b.append(line);
        } finally {
            c.disconnect();
        }
        return b.toString();
    }
}
