package com.miniclip.nativeJNI;

import android.app.AlertDialog;
import android.app.DatePickerDialog;
import android.app.Dialog;
import android.content.DialogInterface;
import android.content.Intent;
import android.content.SharedPreferences;
import android.graphics.Point;
import android.net.ConnectivityManager;
import android.net.NetworkInfo;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.HandlerThread;
import android.os.Looper;
import android.os.Message;
import android.os.StatFs;
import android.os.Vibrator;
import android.support.v4.media.session.PlaybackStateCompat;
import android.text.ClipboardManager;
import android.util.Log;
import android.view.KeyEvent;
import android.widget.DatePicker;
import androidx.webkit.internal.AssetHelper;
import com.google.android.gms.ads.identifier.AdvertisingIdClient;
import com.miniclip.framework.MiniclipAndroidActivity;
import com.miniclip.framework.ThreadingContext;
import com.miniclip.input.BackButtonHandler;
import com.miniclip.nativeJNI.HtmlDialog;
import com.miniclip.nativeJNI.PortsTechnologyHelpers;
import com.miniclip.platform.MCApplication;
import com.miniclip.platform.MCViewManager;
import com.miniclip.utils.ErrorDialog;
import com.miniclip.utils.ResourcesUtils;
import io.ktor.sse.ServerSentEventKt;
import java.io.File;
import java.net.Inet4Address;
import java.net.InetAddress;
import java.net.NetworkInterface;
import java.net.URLEncoder;
import java.util.Collections;
import java.util.Date;
import java.util.GregorianCalendar;
import java.util.HashMap;
import java.util.Iterator;
import java.util.Map;
import java.util.concurrent.atomic.AtomicBoolean;
import loQPY7.eNneC6.wMLhN2;
import net.pubnative.lite.sdk.models.AdExperience;

public class cocojava extends MiniclipAndroidActivity implements DialogInterface.OnClickListener, PortsTechnologyHelpers.PortsTechnologyActivity {
    static final /* synthetic */ boolean $assertionsDisabled = false;
    private static final int HANDLER_CLOSE_DIALOG = 3;
    private static final int HANDLER_EXIT_APP = 5;
    private static final int HANDLER_HIDE_DIALOG = 2;
    private static final int HANDLER_OPEN_URL = 4;
    private static final int HANDLER_SHOW_DIALOG = 1;
    protected static String mAdvertisingID = null;
    public static MiniclipAndroidActivity mContext = null;
    private static Handler mHandler = null;
    /* access modifiers changed from: private */
    public static String mNATIVE_LIBRARY_NAME = "game";
    private static long mStartupTime = System.nanoTime();
    protected static Runnable mUpdateRunable;
    protected static boolean mUpdateRunableCall;
    /* access modifiers changed from: private */
    public HashMap<Integer, Dialog> mDialogs;
    /* access modifiers changed from: private */
    public final AtomicBoolean mDidNativeLibraryLoad = new AtomicBoolean(false);
    /* access modifiers changed from: private */
    public final AtomicBoolean mIsNativeLibraryLoading = new AtomicBoolean(false);

    public static class AssertException extends RuntimeException {
        public AssertException(String str, int i, String str2) {
            super("File: " + str + " Line: " + i + " Function: " + str2);
        }
    }

    public static int DeviceSupportsMultitouch() {
        return mContext.getPackageManager().hasSystemFeature("android.hardware.touchscreen.multitouch") ? 1 : 0;
    }

    public static int SharedPreferences_getInt(String str) {
        return mContext.getSharedPreferences("GAME_INFO", 0).getInt(str, 0);
    }

    public static String SharedPreferences_getString(String str) {
        MiniclipAndroidActivity miniclipAndroidActivity = mContext;
        return miniclipAndroidActivity == null ? "" : miniclipAndroidActivity.getSharedPreferences("GAME_INFO", 0).getString(str, "");
    }

    public static void SharedPreferences_setInt(String str, int i) {
        SharedPreferences.Editor edit = mContext.getSharedPreferences("GAME_INFO", 0).edit();
        edit.putInt(str, i);
        edit.apply();
    }

    public static void SharedPreferences_setString(String str, String str2) {
        try {
            SharedPreferences.Editor edit = mContext.getSharedPreferences("GAME_INFO", 0).edit();
            edit.putString(str, str2);
            edit.apply();
        } catch (Exception unused) {
            Log.i("JAVAINFO", "Failed to set user defaults: key = " + str + " value= " + str2);
        }
    }

    public static float availableMemoryOnDevice() {
        StatFs statFs = new StatFs(mContext.getFilesDir().getAbsolutePath());
        statFs.getBlockCount();
        statFs.getAvailableBlocks();
        return (float) ((((long) statFs.getFreeBlocks()) * ((long) statFs.getBlockSize())) / PlaybackStateCompat.ACTION_SET_CAPTIONING_ENABLED);
    }

    public static void cleanAndroidData() {
        SharedPreferences_setString("APP_VERSION_NUMBER", "0");
        System.exit(0);
    }

    public static void closeMessageBox(int i) {
        Message message = new Message();
        message.what = 3;
        message.obj = new DialogMessage(i);
        mHandler.sendMessage(message);
    }

    public static void deleteDirectory(File file) {
        if (file.isDirectory()) {
            for (File file2 : file.listFiles()) {
                if (file2.isFile()) {
                    Log.i("Activity", file2.getName());
                    file2.delete();
                } else {
                    deleteDirectory(file2);
                }
            }
            file.delete();
        }
    }

    public static void displayErrorMessage(String str, String str2) {
        ErrorDialog.displayErrorMessage(str, str2);
    }

    public static void displayInfoMessage(String str, String str2) {
        ErrorDialog.displayInfoMessage(str, str2);
    }

    public static void displayLowStorageMessage() {
        displayLowStorageMessage(1005);
    }

    public static void displayLowStorageMessage(int i) {
        ErrorDialog.displayLowStorageMessage(i);
    }

    public static void displayMCApplicationErrorMessage() {
        ErrorDialog.displayMCApplicationErrorMessage();
    }

    public static void displayWaitingForOperationMessage() {
        ErrorDialog.displayWaitingForOperationMessage();
    }

    public static void exitApplication() {
        Message message = new Message();
        message.what = 5;
        mHandler.sendMessage(message);
    }

    public static int getAndroidVersion() {
        return Build.VERSION.SDK_INT;
    }

    public static String getAppVersionNumber() {
        return MCApplication.getApplicationVersionNumber();
    }

    public static String getDeviceInfo(String str) {
        if (str.contentEquals("device")) {
            return Build.DEVICE;
        }
        if (str.contentEquals("version")) {
            return Build.VERSION.RELEASE;
        }
        if (str.contentEquals(AdExperience.BRAND)) {
            return Build.BRAND;
        }
        if (!str.contentEquals("name")) {
            return "";
        }
        String str2 = Build.MANUFACTURER;
        String str3 = Build.MODEL;
        if (str3.startsWith(str2)) {
            return str3;
        }
        return str2 + ServerSentEventKt.SPACE + str3;
    }

    public static long getElapsedTimeSinceStartup() {
        return System.nanoTime() - mStartupTime;
    }

    public static String getGoogleAdvertisingID() {
        String str = mAdvertisingID;
        return str != null ? str : "";
    }

    public static String getIPAddress(boolean z) {
        try {
            for (T inetAddresses : Collections.list(NetworkInterface.getNetworkInterfaces())) {
                Iterator<T> it = Collections.list(inetAddresses.getInetAddresses()).iterator();
                while (true) {
                    if (it.hasNext()) {
                        InetAddress inetAddress = (InetAddress) it.next();
                        if (!inetAddress.isLoopbackAddress()) {
                            String upperCase = inetAddress.getHostAddress().toUpperCase();
                            boolean z2 = inetAddress instanceof Inet4Address;
                            if (z && z2) {
                                return upperCase;
                            }
                            if (!z && !z2) {
                                int indexOf = upperCase.indexOf(37);
                                return indexOf < 0 ? upperCase : upperCase.substring(0, indexOf);
                            }
                        }
                    }
                }
            }
            return "";
        } catch (Exception unused) {
            return "";
        }
    }

    public static String getResourceString(String str, String str2) {
        return ResourcesUtils.getResourceString(str, str2);
    }

    public static Point getScreenSize() {
        Point point = new Point();
        point.x = MCViewManager.displayWidth;
        point.y = MCViewManager.displayHeight;
        return point;
    }

    public static int getTimezoneOffset() {
        return new GregorianCalendar().getTimeZone().getOffset(new Date().getTime()) / 1000;
    }

    public static void hideMessageBox(int i) {
        Message message = new Message();
        message.what = 2;
        message.obj = new DialogMessage(i);
        mHandler.sendMessage(message);
    }

    public static boolean isInstalledOnSdCard() {
        try {
            String absolutePath = mContext.getFilesDir().getAbsolutePath();
            if (absolutePath.startsWith("/data/")) {
                return false;
            }
            return absolutePath.contains("/mnt/") || absolutePath.contains("/sdcard/");
        } catch (Throwable unused) {
            return false;
        }
    }

    public static void java_assert(String str, int i, String str2) {
    }

    public static void openLink(String str) {
        Message message = new Message();
        message.what = 4;
        message.obj = str;
        mHandler.sendMessage(message);
    }

    /* access modifiers changed from: private */
    public void openURL(String str) {
        if (!isOnline()) {
            showNoInternetDialog();
            return;
        }
        try {
            Intent intent = new Intent("android.intent.action.VIEW");
            intent.setData(Uri.parse(str));
            startActivity(intent);
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    public static String percentEncodeUTF8(String str) {
        try {
            return URLEncoder.encode(str, "UTF-8");
        } catch (Exception e) {
            e.printStackTrace();
            return "";
        }
    }

    public static void sendEmail(final String str, final String str2, final String str3) {
        mContext.runOnUiThread(new Runnable() {
            public void run() {
                Intent intent = new Intent("android.intent.action.SEND");
                intent.setType(AssetHelper.DEFAULT_MIME_TYPE);
                String str = str;
                if (str != null) {
                    intent.putExtra("android.intent.extra.EMAIL", new String[]{str});
                }
                String str2 = str2;
                if (str2 != null) {
                    intent.putExtra("android.intent.extra.SUBJECT", str2);
                }
                String str3 = str3;
                if (str3 != null) {
                    intent.putExtra("android.intent.extra.TEXT", str3);
                }
                cocojava.mContext.startActivity(Intent.createChooser(intent, "Send email..."));
            }
        });
    }

    public static void setClipboardText(final String str) {
        mContext.runOnUiThread(new Runnable() {
            public void run() {
                ((ClipboardManager) cocojava.mContext.getSystemService("clipboard")).setText(str);
            }
        });
    }

    protected static synchronized void setNativeLibraryName(String str) {
        synchronized (cocojava.class) {
            mNATIVE_LIBRARY_NAME = str;
        }
    }

    public static void showDatePickerDialog_JNI(final int i, final int i2, final int i3) {
        mContext.runOnUiThread(new Runnable() {
            public void run() {
                ((cocojava) cocojava.mContext).showDatePickerDialog(i, i2, i3);
            }
        });
    }

    public static void showDatePickerDialog_JNI(final int i, final int i2, final int i3, final int i4) {
        mContext.runOnUiThread(new Runnable() {
            public void run() {
                ((cocojava) cocojava.mContext).showDatePickerDialog(i, i2, i3, i4);
            }
        });
    }

    /* access modifiers changed from: private */
    public void showDialog(int i, String str, String str2, final boolean z, String[] strArr) {
        Dialog dialog;
        if (!this.mDialogs.containsKey(Integer.valueOf(i))) {
            int i2 = 0;
            if (strArr.length < 4) {
                AlertDialog.Builder message = new AlertDialog.Builder(this).setTitle(str).setMessage(str2);
                while (i2 < strArr.length) {
                    if (int2ButtonValue(i2) == -1) {
                        message.setPositiveButton(strArr[i2], this);
                    } else if (int2ButtonValue(i2) == -2) {
                        message.setNegativeButton(strArr[i2], this);
                    } else if (int2ButtonValue(i2) == -3) {
                        message.setNeutralButton(strArr[i2], this);
                    }
                    i2++;
                }
                dialog = message.create();
            } else {
                AlertDialog create = new AlertDialog.Builder(this).create();
                create.setTitle(str);
                AlertDialog alertDialog = create;
                create.setMessage(str2);
                while (i2 < strArr.length) {
                    create.setButton(int2ButtonValue(i2), strArr[i2], this);
                    i2++;
                }
                dialog = create;
            }
            this.mDialogs.put(Integer.valueOf(i), dialog);
        } else {
            dialog = this.mDialogs.get(Integer.valueOf(i));
        }
        dialog.setOnKeyListener(new DialogInterface.OnKeyListener() {
            public boolean onKey(DialogInterface dialogInterface, int i, KeyEvent keyEvent) {
                if (!z || i != 4 || keyEvent.getAction() != 0) {
                    return false;
                }
                Log.i("key down", "KEYCODE_BACK");
                for (Map.Entry entry : cocojava.this.mDialogs.entrySet()) {
                    if (entry.getValue() == dialogInterface) {
                        cocojava.this.closeDialog(((Integer) entry.getKey()).intValue());
                        return true;
                    }
                }
                return false;
            }
        });
        dialog.setCancelable(z);
        dialog.show();
    }

    public static void showHtmlDialog(final String str, final int i) {
        mContext.runOnUiThread(new Runnable() {
            public void run() {
                new HtmlDialog(cocojava.mContext, str, i, new HtmlDialog.HtmlDialogListener() {
                    public void onCancel() {
                    }

                    public void onComplete() {
                    }

                    public void onError() {
                    }
                }).show();
            }
        });
    }

    public static void showMessageBox(int i, String str, String str2, boolean z, String[] strArr) {
        DialogMessage dialogMessage = new DialogMessage(i);
        dialogMessage.title = str;
        dialogMessage.message = str2;
        dialogMessage.cancelable = z;
        dialogMessage.buttonTitles = strArr;
        Message message = new Message();
        message.what = 1;
        message.obj = dialogMessage;
        mHandler.sendMessage(message);
    }

    private void showNoInternetDialog() {
        AlertDialog.Builder builder = new AlertDialog.Builder(this);
        builder.setMessage("Please enable internet").setCancelable(false).setPositiveButton("Back", new DialogInterface.OnClickListener() {
            public void onClick(DialogInterface dialogInterface, int i) {
                dialogInterface.cancel();
            }
        });
        builder.create().show();
    }

    public static void vibrateForMS(int i) {
        ((Vibrator) mContext.getSystemService("vibrator")).vibrate((long) i);
    }

    /* access modifiers changed from: package-private */
    public int buttonValue2Int(int i) {
        if (i != -3) {
            return i != -2 ? 0 : 1;
        }
        return 2;
    }

    /* access modifiers changed from: package-private */
    public void closeDialog(int i) {
        if (this.mDialogs.containsKey(Integer.valueOf(i))) {
            Dialog dialog = this.mDialogs.get(Integer.valueOf(i));
            if (dialog != null) {
                dialog.dismiss();
            }
            this.mDialogs.remove(Integer.valueOf(i));
        }
    }

    public boolean didLoadNativeLibrary() {
        return this.mDidNativeLibraryLoad.get();
    }

    public void firstRun() {
        Log.i("JAVAINFO", "firstRun");
        if (MCApplication.hasFatalErrorOccurred()) {
            displayMCApplicationErrorMessage();
        } else if (!this.mDidNativeLibraryLoad.get()) {
            if (!this.mIsNativeLibraryLoading.get()) {
                loadNativeLibrary();
            }
            new Handler(Looper.getMainLooper()).postDelayed(new Runnable() {
                public void run() {
                    cocojava.this.queueEvent(ThreadingContext.AndroidUi, new Runnable() {
                        public void run() {
                            cocojava.this.firstRun();
                        }
                    });
                }
            }, 50);
        } else {
            try {
                MCApplication.firstRun();
                mHandler = new Handler() {
                    public void handleMessage(Message message) {
                        int i = message.what;
                        if (i == 1) {
                            DialogMessage dialogMessage = (DialogMessage) message.obj;
                            cocojava.this.showDialog(dialogMessage.msgId, dialogMessage.title, dialogMessage.message, dialogMessage.cancelable, dialogMessage.buttonTitles);
                        } else if (i == 2) {
                            cocojava.this.hideDialog(((DialogMessage) message.obj).msgId);
                        } else if (i == 3) {
                            cocojava.this.closeDialog(((DialogMessage) message.obj).msgId);
                        } else if (i == 4) {
                            cocojava.this.openURL((String) message.obj);
                        } else if (i == 5) {
                            cocojava.this.finish();
                        }
                    }
                };
                this.mDialogs = new HashMap<>();
            } catch (Throwable th) {
                Log.i("JAVAINFO", "Initial Load Failed");
                th.printStackTrace();
                MCApplication.signalFatalError(1003);
                displayMCApplicationErrorMessage();
            }
        }
    }

    /* access modifiers changed from: package-private */
    public void hideDialog(int i) {
        closeDialog(i);
    }

    /* access modifiers changed from: package-private */
    public int int2ButtonValue(int i) {
        if (i != 0) {
            return (i == 1 || i != 2) ? -2 : -3;
        }
        return -1;
    }

    public boolean isOnline() {
        NetworkInfo activeNetworkInfo = ((ConnectivityManager) getSystemService("connectivity")).getActiveNetworkInfo();
        if (activeNetworkInfo == null) {
            return false;
        }
        return activeNetworkInfo.isConnectedOrConnecting();
    }

    /* access modifiers changed from: protected */
    public void loadNativeLibrary() {
        this.mIsNativeLibraryLoading.set(true);
        final HandlerThread handlerThread = new HandlerThread("LoadingThread");
        handlerThread.start();
        new Handler(handlerThread.getLooper()).post(new Runnable() {
            public void run() {
                try {
                    wMLhN2.nkZ9d4(cocojava.mNATIVE_LIBRARY_NAME);
                    cocojava.this.mDidNativeLibraryLoad.set(true);
                } catch (UnsatisfiedLinkError e) {
                    Log.i("MiniclipAndroidActivity", "Library Load Failed");
                    e.printStackTrace();
                    cocojava.this.runOnUiThread(new Runnable() {
                        public void run() {
                            cocojava.displayLowStorageMessage(1002);
                        }
                    });
                } catch (Throwable th) {
                    cocojava.this.mIsNativeLibraryLoading.set(false);
                    handlerThread.quitSafely();
                    throw th;
                }
                cocojava.this.mIsNativeLibraryLoading.set(false);
                handlerThread.quitSafely();
            }
        });
    }

    public void onClick(DialogInterface dialogInterface, int i) {
        for (Map.Entry next : this.mDialogs.entrySet()) {
            if (next.getValue() == dialogInterface) {
                CocoJNI.MonMessageBoxButtonPressed(((Integer) next.getKey()).intValue(), buttonValue2Int(i));
                return;
            }
        }
    }

    public void onCreate(Bundle bundle) {
        super.onCreate(bundle);
        BackButtonHandler.init(this);
        MCApplication.init();
        PortsTechnologyHelpers.portsTechnologyActivity = this;
        mContext = this;
        setVolumeControlStream(3);
        new Thread(new Runnable() {
            public void run() {
                try {
                    cocojava.mAdvertisingID = AdvertisingIdClient.getAdvertisingIdInfo(cocojava.this).getId();
                } catch (Exception unused) {
                }
            }
        }).start();
    }

    /* access modifiers changed from: protected */
    public void onDestroy() {
        super.onDestroy();
        System.exit(0);
    }

    public void onWindowFocusChanged(boolean z) {
        super.onWindowFocusChanged(z);
    }

    public void showDatePickerDialog(int i, int i2, int i3) {
        showDatePickerDialog(i, i2, i3, 0);
    }

    public void showDatePickerDialog(int i, int i2, int i3, int i4) {
        int i5 = i2 - 1;
        if (i4 == 0) {
            i4 = mContext.getResources().getIdentifier("DatePicker", "style", mContext.getPackageName());
        }
        DatePickerDialog datePickerDialog = new DatePickerDialog(mContext, i4, new DatePickerDialog.OnDateSetListener() {
            public void onDateSet(DatePicker datePicker, final int i, final int i2, final int i3) {
                cocojava.this.queueEvent(ThreadingContext.Main, new Runnable() {
                    public void run() {
                        CocoJNI.MdatePickerResponce(i, i2 + 1, i3);
                        CocoJNI.MdatePickerClosed();
                    }
                });
            }
        }, i, i5, i3);
        datePickerDialog.setOnDismissListener(new DialogInterface.OnDismissListener() {
            public void onDismiss(DialogInterface dialogInterface) {
                cocojava.this.queueEvent(ThreadingContext.Main, new Runnable() {
                    public void run() {
                        CocoJNI.MdatePickerClosed();
                    }
                });
            }
        });
        datePickerDialog.show();
    }
}
