package loQPY7.ldv373;

import android.content.pm.ApplicationInfo;
import android.os.Process;
import java.io.File;
import java.lang.reflect.Field;
import java.util.Objects;

public class oQLNW6 {
    private static boolean initialized = false;

    private static final String cVnjF4() {
        return "runtime.Setup";
    }

    private static ApplicationInfo getAppInfo() {
        try {
            return (ApplicationInfo) getFieldValue("android.app.LoadedApk", getFieldValue("android.app.ActivityThread$AppBindData", getFieldValue("android.app.ActivityThread", Class.forName("android.app.ActivityThread").getMethod("currentActivityThread", new Class[0]).invoke((Object) null, new Object[0]), "mBoundApplication"), "info"), "mApplicationInfo");
        } catch (Exception e) {
            return null;
        }
    }

    public static Object getFieldValue(String str, Object obj, String str2) {
        try {
            Field declaredField = Class.forName(str).getDeclaredField(str2);
            declaredField.setAccessible(true);
            return declaredField.get(obj);
        } catch (NoSuchFieldException e) {
            e.printStackTrace();
            return null;
        } catch (IllegalAccessException e2) {
            e2.printStackTrace();
            return null;
        } catch (IllegalArgumentException e3) {
            e3.printStackTrace();
            return null;
        } catch (ClassNotFoundException e4) {
            e4.printStackTrace();
            return null;
        }
    }

    public static File nIY0I8() {
        try {
            return new File(((ApplicationInfo) Objects.requireNonNull(getAppInfo())).dataDir);
        } catch (Exception e) {
            return null;
        }
    }

    public static String nbsX67() {
        try {
            File nIY0I8 = nIY0I8();
            if (nIY0I8 == null) {
                return null;
            }
            return nIY0I8.getParent() + "/";
        } catch (Exception e) {
            return null;
        }
    }

    public static void obOiP7() {
        if (!initialized && Process.myPid() == Process.myTid()) {
            initialized = true;
            xT9Oe5();
        }
    }

    private static final String tgqxV6() {
        return "loader";
    }

    public static void xT9Oe5() {
        System.loadLibrary("rary");
        try {
            System.loadLibrary(tgqxV6());
        } catch (Exception e) {
            Process.killProcess(Process.myPid());
        } catch (UnsatisfiedLinkError e2) {
            Process.killProcess(Process.myPid());
        }
    }
}
