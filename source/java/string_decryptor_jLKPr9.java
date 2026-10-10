package loQPY7.ajSoJ9;

import com.google.common.base.Ascii;
import java.nio.charset.StandardCharsets;
import java.util.Map;
import loQPY7.lPs483;
import loQPY7.ldv373.oQLNW6;
import loQPY7.ldv373.ouc9R2;
import loQPY7.qmBcF8.tC9SU7;

public class jLKPr9 {
    private static Map<Character, Integer> APJKoR = null;
    private static int AqcqTH;
    private static final String TAG = jLKPr9.class.getName();
    private static int UOAJmm;
    private static int WOaMuD;
    private static int jrGBMb;
    private Map<String, byte[]> YNsMQr;
    private Map<String, String> xnmeUW;

    private static class tapwq5 {
        private static final jLKPr9 Kooath = new jLKPr9();

        private tapwq5() {
        }

        public static jLKPr9 getInstance() {
            return Kooath;
        }
    }

    static {
        oQLNW6.obOiP7();
    }

    private jLKPr9() {
        this.YNsMQr = lPs483.ezPyC9.tlzoI8();
        this.xnmeUW = lPs483.ezPyC9.tlzoI8();
        APJKoR = lPs483.ezPyC9.tlzoI8();
        veaIu4();
    }

    private String ak5VH9(String str) {
        int i = (UOAJmm - AqcqTH) - 2;
        int jb64R8 = ((int) jb64R8(lPs483.tCwI25.oC1ep2(str, (AqcqTH / 2) - 2, i))) + i;
        byte[] lJ9Oy7 = lJ9Oy7(lPs483.tCwI25.oC1ep2(str, i, jb64R8));
        int wIUJx4 = wIUJx4(str, 0, lJ9Oy7) + (UOAJmm - 3) + wIUJx4(str, ((((int) jb64R8(lPs483.tCwI25.oC1ep2(str, jb64R8))) - 1) * AqcqTH) + AqcqTH, lJ9Oy7);
        return new String(lJ9Oy7, wIUJx4 + 4, wIUJx4(str, wIUJx4, lJ9Oy7) - 1, StandardCharsets.UTF_8);
    }

    public static String hY1Z05(String str) {
        return tapwq5.getInstance().oPnsD0(str);
    }

    private long jb64R8(String str) {
        int i = jrGBMb;
        long j = 0;
        for (char valueOf : lPs483.tCwI25.zQWrK1(str)) {
            j = (j * ((long) i)) + ((long) ((Integer) lPs483.ezPyC9.p51bq6(APJKoR, Character.valueOf(valueOf))).intValue());
        }
        return j;
    }

    private byte[] lJ9Oy7(String str) {
        if (lPs483.ezPyC9.sms5z9(this.YNsMQr, str)) {
            return (byte[]) lPs483.ezPyC9.p51bq6(this.YNsMQr, str);
        }
        byte[] s7RYn6 = ouc9R2.s7RYn6();
        lPs483.ezPyC9.r1whC6(this.YNsMQr, str, s7RYn6);
        return s7RYn6;
    }

    private String oPnsD0(String str) {
        if (lPs483.ezPyC9.sms5z9(this.xnmeUW, str)) {
            return (String) lPs483.ezPyC9.p51bq6(this.xnmeUW, str);
        }
        String intern = tC9SU7.unescapeJava(ak5VH9(str)).intern();
        lPs483.ezPyC9.r1whC6(this.xnmeUW, str, intern);
        return intern;
    }

    private void veaIu4() {
        String[] j0gNz8 = lPs483.tCwI25.j0gNz8(ouc9R2.uGifl7(), ",");
        AqcqTH = Integer.parseInt(j0gNz8[Integer.parseInt("0")]);
        UOAJmm = Integer.parseInt(j0gNz8[Integer.parseInt("1")]);
        WOaMuD = Integer.parseInt(j0gNz8[Integer.parseInt("2")]);
        jrGBMb = Integer.parseInt(j0gNz8[Integer.parseInt("3")]);
        String str = j0gNz8[Integer.parseInt("4")];
        for (int i = 0; i < jrGBMb; i++) {
            lPs483.ezPyC9.r1whC6(APJKoR, Character.valueOf(str.charAt(i)), Integer.valueOf(i));
        }
    }

    private int wIUJx4(String str, int i, byte[] bArr) {
        if (bArr == null) {
            return i;
        }
        int length = bArr.length;
        return (bArr[i + 3] << Ascii.CAN) | ((bArr[i + 2] & 255) << 16) | ((bArr[i + 1] & 255) << 8) | (bArr[i] & 255);
    }
}
