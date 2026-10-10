package com.miniclip.network;

import android.app.Activity;
import android.util.Log;
import androidx.browser.trusted.sharing.ShareTarget;
import com.google.android.gms.net.CronetProviderInstaller;
import com.miniclip.framework.Miniclip;
import io.ktor.sse.ServerSentEventKt;
import java.io.BufferedInputStream;
import java.io.ByteArrayOutputStream;
import java.io.File;
import java.io.FileNotFoundException;
import java.io.FileOutputStream;
import java.io.IOException;
import java.io.OutputStream;
import java.io.UnsupportedEncodingException;
import java.net.HttpURLConnection;
import java.net.MalformedURLException;
import java.net.URL;
import java.net.UnknownHostException;
import java.nio.ByteBuffer;
import java.nio.channels.Channels;
import java.nio.channels.WritableByteChannel;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.concurrent.BlockingQueue;
import java.util.concurrent.Executor;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.LinkedBlockingQueue;
import java.util.concurrent.ThreadPoolExecutor;
import java.util.concurrent.TimeUnit;
import org.chromium.net.CronetEngine;
import org.chromium.net.CronetException;
import org.chromium.net.UploadDataProvider;
import org.chromium.net.UploadDataSink;
import org.chromium.net.UrlRequest;
import org.chromium.net.UrlResponseInfo;

public class HttpConnection {
    private static final int CORE_POOL_SIZE;
    private static final int CPU_COUNT;
    public static final int HttpConnectionErrorBadURL = -1000;
    public static final int HttpConnectionErrorUnknown = -1;
    public static final int HttpConnectionErrorUnknownHost = -1003;
    private static final int KEEP_ALIVE = 1;
    private static final int MAXIMUM_POOL_SIZE;
    private static final Executor THREAD_POOL_EXECUTOR;
    private static ExecutorService cronetCallbackExecutorService = null;
    private static CronetEngine cronetEngine = null;
    private static HttpVersion defaultHttpVersion = HttpVersion.HTTP3;
    private static final int defaultTimeout = 60000;
    private static final BlockingQueue<Runnable> sPoolWorkQueue;
    /* access modifiers changed from: private */
    public boolean canceled;
    protected Integer connectionID;
    /* access modifiers changed from: private */
    public int errorCode;
    protected HashMap<String, String> headerProperties;
    protected String httpCommand;
    private HttpVersion httpVersion;
    protected boolean jniCall;
    protected HttpConnectionListener listener;
    protected String saveFilepath;
    protected int timeout;
    protected byte[] urlBody;
    protected String urlString;

    public enum HttpVersion {
        HTTP1_1,
        HTTP2,
        HTTP3;

        public boolean shouldEnableHTTP2() {
            return this == HTTP2 || this == HTTP3;
        }

        public boolean shouldEnableHTTP3() {
            return this == HTTP3;
        }
    }

    private class MCHttpURLConnectionTask {
        String outputFilepath;

        private MCHttpURLConnectionTask() {
            this.outputFilepath = null;
        }

        /* access modifiers changed from: package-private */
        /* JADX WARNING: Code restructure failed: missing block: B:108:0x0229, code lost:
            r13 = e;
         */
        /* JADX WARNING: Code restructure failed: missing block: B:109:0x022b, code lost:
            r13 = e;
         */
        /* JADX WARNING: Code restructure failed: missing block: B:110:0x022d, code lost:
            r13 = e;
         */
        /* JADX WARNING: Code restructure failed: missing block: B:118:0x0240, code lost:
            r1.disconnect();
         */
        /* JADX WARNING: Code restructure failed: missing block: B:125:0x0251, code lost:
            r1.disconnect();
         */
        /* JADX WARNING: Failed to process nested try/catch */
        /* JADX WARNING: Missing exception handler attribute for start block: B:66:0x0179 */
        /* JADX WARNING: Removed duplicated region for block: B:108:0x0229 A[ExcHandler: Exception (e java.lang.Exception), Splitter:B:49:0x011d] */
        /* JADX WARNING: Removed duplicated region for block: B:110:0x022d A[ExcHandler: UnknownHostException (e java.net.UnknownHostException), Splitter:B:8:0x0018] */
        /* JADX WARNING: Removed duplicated region for block: B:118:0x0240  */
        /* JADX WARNING: Removed duplicated region for block: B:125:0x0251  */
        /* JADX WARNING: Removed duplicated region for block: B:135:0x026f  */
        /* JADX WARNING: Removed duplicated region for block: B:17:0x004f A[Catch:{ UnknownHostException -> 0x022d, IOException -> 0x022b, Exception -> 0x0229 }] */
        /* JADX WARNING: Removed duplicated region for block: B:18:0x0065 A[Catch:{ UnknownHostException -> 0x022d, IOException -> 0x022b, Exception -> 0x0229 }] */
        /* JADX WARNING: Removed duplicated region for block: B:22:0x0078 A[Catch:{ UnknownHostException -> 0x022d, IOException -> 0x022b, Exception -> 0x0229 }, LOOP:0: B:20:0x0072->B:22:0x0078, LOOP_END] */
        /* JADX WARNING: Removed duplicated region for block: B:24:0x0090 A[Catch:{ UnknownHostException -> 0x022d, IOException -> 0x022b, Exception -> 0x0229 }] */
        /* JADX WARNING: Removed duplicated region for block: B:33:0x00b0  */
        /* JADX WARNING: Removed duplicated region for block: B:36:0x00b6 A[SYNTHETIC, Splitter:B:36:0x00b6] */
        /* JADX WARNING: Unknown top exception splitter block from list: {B:115:0x0237=Splitter:B:115:0x0237, B:122:0x0248=Splitter:B:122:0x0248, B:129:0x0259=Splitter:B:129:0x0259} */
        public boolean doInBackground(String str) {
            Throwable th;
            HttpURLConnection httpURLConnection;
            boolean z;
            byte[] bArr;
            int i;
            if (HttpConnection.this.canceled) {
                return false;
            }
            try {
                URL url = new URL(str);
                byte[] bArr2 = null;
                try {
                    httpURLConnection = (HttpURLConnection) url.openConnection();
                    try {
                        httpURLConnection.setRequestMethod(HttpConnection.this.httpCommand);
                        httpURLConnection.setReadTimeout(HttpConnection.this.timeout);
                        httpURLConnection.setConnectTimeout(HttpConnection.this.timeout);
                        httpURLConnection.setDoInput(true);
                        if (!HttpConnection.this.httpCommand.equals("POST")) {
                            if (!HttpConnection.this.httpCommand.equals("PUT")) {
                                z = false;
                                if (!z) {
                                    bArr = HttpConnection.this.urlBody;
                                    int length = bArr.length;
                                    httpURLConnection.setRequestProperty("Content-Type", ShareTarget.ENCODING_TYPE_URL_ENCODED);
                                    httpURLConnection.setRequestProperty("Content-Length", Integer.toString(length));
                                } else {
                                    bArr = null;
                                }
                                for (Map.Entry next : HttpConnection.this.headerProperties.entrySet()) {
                                    httpURLConnection.setRequestProperty((String) next.getKey(), (String) next.getValue());
                                }
                                if (z) {
                                    if (HttpConnection.this.canceled) {
                                        if (httpURLConnection != null) {
                                            httpURLConnection.disconnect();
                                        }
                                        return false;
                                    }
                                    httpURLConnection.setDoOutput(true);
                                    httpURLConnection.getOutputStream().write(bArr);
                                }
                                if (!HttpConnection.this.canceled) {
                                    if (httpURLConnection != null) {
                                        httpURLConnection.disconnect();
                                    }
                                    return false;
                                }
                                int unused = HttpConnection.this.errorCode = httpURLConnection.getResponseCode();
                                if (HttpConnection.this.errorCode != 200) {
                                    Log.e("HttpConnection", "Server returned HTTP " + HttpConnection.this.errorCode + ServerSentEventKt.SPACE + httpURLConnection.getResponseMessage());
                                    if (HttpConnection.this.errorCode == 302 || HttpConnection.this.errorCode == 301 || HttpConnection.this.errorCode == 303) {
                                        boolean doInBackground = doInBackground(httpURLConnection.getHeaderField("Location"));
                                        if (httpURLConnection != null) {
                                            httpURLConnection.disconnect();
                                        }
                                        return doInBackground;
                                    }
                                }
                                Map headerFields = httpURLConnection.getHeaderFields();
                                HashMap hashMap = new HashMap();
                                for (Map.Entry entry : headerFields.entrySet()) {
                                    List list = (List) entry.getValue();
                                    if (!list.isEmpty()) {
                                        if (entry.getKey() != null) {
                                            hashMap.put((String) entry.getKey(), (String) list.iterator().next());
                                        }
                                    }
                                }
                                if (HttpConnection.this.canceled) {
                                    if (httpURLConnection != null) {
                                        httpURLConnection.disconnect();
                                    }
                                    return false;
                                }
                                BufferedInputStream bufferedInputStream = new BufferedInputStream(httpURLConnection.getInputStream());
                                bufferedInputStream = new BufferedInputStream(httpURLConnection.getErrorStream());
                                byte[] bArr3 = new byte[1024];
                                if (this.outputFilepath != null) {
                                    File file = new File(this.outputFilepath);
                                    File parentFile = file.getParentFile();
                                    if (!parentFile.exists() && !parentFile.mkdirs()) {
                                        Log.e("HttpConnection", "Failed to create parent directory for file download");
                                    }
                                    FileOutputStream fileOutputStream = new FileOutputStream(file);
                                    while (true) {
                                        int read = bufferedInputStream.read(bArr3);
                                        if (read == -1) {
                                            fileOutputStream.flush();
                                            fileOutputStream.close();
                                            i = 0;
                                            break;
                                        } else if (HttpConnection.this.canceled) {
                                            if (httpURLConnection != null) {
                                                httpURLConnection.disconnect();
                                            }
                                            return false;
                                        } else {
                                            fileOutputStream.write(bArr3, 0, read);
                                        }
                                    }
                                } else {
                                    ByteArrayOutputStream byteArrayOutputStream = new ByteArrayOutputStream();
                                    while (!HttpConnection.this.canceled) {
                                        int read2 = bufferedInputStream.read(bArr3);
                                        if (read2 == -1) {
                                            byte[] byteArray = byteArrayOutputStream.toByteArray();
                                            i = byteArray.length;
                                            byteArrayOutputStream.close();
                                            bArr2 = byteArray;
                                        } else {
                                            byteArrayOutputStream.write(bArr3, 0, read2);
                                        }
                                    }
                                    if (httpURLConnection != null) {
                                        httpURLConnection.disconnect();
                                    }
                                    return false;
                                }
                                bufferedInputStream.close();
                                if (HttpConnection.this.jniCall) {
                                    HttpConnection.downloadComplete(HttpConnection.this.connectionID.intValue(), HttpConnection.this.errorCode, hashMap, i, bArr2);
                                }
                                if (HttpConnection.this.listener != null) {
                                    HttpConnection.this.listener.downloadComplete(HttpConnection.this.connectionID.intValue(), i, bArr2);
                                }
                                if (httpURLConnection != null) {
                                    httpURLConnection.disconnect();
                                }
                                return true;
                            }
                        }
                        z = true;
                        if (!z) {
                        }
                        while (r6.hasNext()) {
                        }
                        if (z) {
                        }
                        if (!HttpConnection.this.canceled) {
                        }
                    } catch (IOException ) {
                        bufferedInputStream = new BufferedInputStream(httpURLConnection.getErrorStream());
                    } catch (UnknownHostException e) {
                    } catch (Exception e2) {
                    }
                } catch (UnknownHostException e3) {
                    UnknownHostException unknownHostException = e3;
                    httpURLConnection = null;
                    UnknownHostException e4 = unknownHostException;
                    Log.e("HttpConnection", e4.toString());
                    int unused2 = HttpConnection.this.errorCode = -1003;
                    if (httpURLConnection != null) {
                        httpURLConnection.disconnect();
                    }
                    return false;
                } catch (IOException e5) {
                    IOException iOException = e5;
                    httpURLConnection = null;
                    IOException e6 = iOException;
                    Log.e("HttpConnection", e6.toString());
                    if (httpURLConnection != null) {
                    }
                    return false;
                } catch (Exception e7) {
                    Exception exc = e7;
                    httpURLConnection = null;
                    Exception e8 = exc;
                    try {
                        Log.e("HttpConnection", e8.toString());
                        if (httpURLConnection != null) {
                        }
                        return false;
                    } catch (Throwable th2) {
                        th = th2;
                        if (httpURLConnection != null) {
                            httpURLConnection.disconnect();
                        }
                        throw th;
                    }
                } catch (Throwable th3) {
                    httpURLConnection = null;
                    th = th3;
                    if (httpURLConnection != null) {
                    }
                    throw th;
                }
            } catch (MalformedURLException e9) {
                Log.e("HttpConnection", "Invalid URL: " + str + " - " + e9);
                int unused3 = HttpConnection.this.errorCode = -1000;
                return false;
            }
        }

        /* access modifiers changed from: package-private */
        public void onPostExecute(boolean z) {
            if (z) {
                HttpConnectionsManager.sharedInstance().removeConnection(HttpConnection.this.connectionID);
                return;
            }
            String str = "MCHttpURLConnectionTask failed to " + HttpConnection.this.httpCommand + " - " + HttpConnection.this.urlString;
            Log.e("HttpConnection", str);
            if (HttpConnection.this.jniCall) {
                HttpConnection.downloadFailed(HttpConnection.this.connectionID.intValue(), str, HttpConnection.this.errorCode);
            }
            if (HttpConnection.this.listener != null) {
                HttpConnection.this.listener.downloadFailed(HttpConnection.this.connectionID.intValue(), str);
            }
            if (this.outputFilepath != null) {
                try {
                    new File(this.outputFilepath).delete();
                } catch (Exception e) {
                    Log.e("HttpConnection", e.toString());
                }
            }
        }
    }

    private static class PostDataProvider extends UploadDataProvider {
        byte[] data;

        public PostDataProvider(byte[] bArr) {
            this.data = bArr;
        }

        public long getLength() throws IOException {
            return (long) this.data.length;
        }

        public void read(UploadDataSink uploadDataSink, ByteBuffer byteBuffer) throws IOException {
            byteBuffer.put(this.data);
            uploadDataSink.onReadSucceeded(false);
        }

        public void rewind(UploadDataSink uploadDataSink) throws IOException {
            uploadDataSink.onRewindSucceeded();
        }
    }

    private class UrlRequestCallback extends UrlRequest.Callback {
        private final ByteArrayOutputStream bytesReceived;
        private OutputStream fileOutputStream = null;
        private final WritableByteChannel receiveChannel;
        private final String saveFilePath;
        private final long startTimeNanos = System.nanoTime();

        UrlRequestCallback(String str) {
            ByteArrayOutputStream byteArrayOutputStream = new ByteArrayOutputStream();
            this.bytesReceived = byteArrayOutputStream;
            this.receiveChannel = Channels.newChannel(byteArrayOutputStream);
            this.saveFilePath = str;
        }

        public void onCanceled(UrlRequest urlRequest, UrlResponseInfo urlResponseInfo) {
            if (HttpConnection.this.jniCall) {
                HttpConnection.downloadFailed(HttpConnection.this.connectionID.intValue(), "Request canceled", urlResponseInfo.getHttpStatusCode());
            }
            if (HttpConnection.this.listener != null) {
                HttpConnection.this.listener.downloadFailed(HttpConnection.this.connectionID.intValue(), "Request canceled");
            }
        }

        public void onFailed(UrlRequest urlRequest, UrlResponseInfo urlResponseInfo, CronetException cronetException) {
            int httpStatusCode = urlResponseInfo != null ? urlResponseInfo.getHttpStatusCode() : 400;
            String message = cronetException != null ? cronetException.getMessage() : "Unknown error";
            if (HttpConnection.this.jniCall) {
                HttpConnection.downloadFailed(HttpConnection.this.connectionID.intValue(), message, httpStatusCode);
            }
            if (HttpConnection.this.listener != null) {
                HttpConnection.this.listener.downloadFailed(HttpConnection.this.connectionID.intValue(), message);
            }
        }

        public void onReadCompleted(UrlRequest urlRequest, UrlResponseInfo urlResponseInfo, ByteBuffer byteBuffer) {
            byteBuffer.flip();
            try {
                this.receiveChannel.write(byteBuffer);
            } catch (IOException e) {
                Log.e("HttpConnection", "[onReadCompleted] IOException during ByteBuffer read. Details: ", e);
            }
            this.bytesReceived.write(byteBuffer.array(), 0, byteBuffer.remaining());
            byteBuffer.clear();
            urlRequest.read(byteBuffer);
        }

        public void onRedirectReceived(UrlRequest urlRequest, UrlResponseInfo urlResponseInfo, String str) throws Exception {
            urlRequest.followRedirect();
        }

        public void onResponseStarted(UrlRequest urlRequest, UrlResponseInfo urlResponseInfo) throws Exception {
            if (urlResponseInfo.getHttpStatusCode() >= 200 && urlResponseInfo.getHttpStatusCode() <= 204 && this.saveFilePath != null) {
                try {
                    new File(this.saveFilePath).delete();
                } catch (Exception e) {
                    Log.e("HttpConnection", e.toString());
                }
                File file = new File(this.saveFilePath);
                File parentFile = file.getParentFile();
                if (!parentFile.exists() && !parentFile.mkdirs()) {
                    Log.e("HttpConnection", "[onResponseStarted] Failed to create parent directory for file download");
                }
                try {
                    this.fileOutputStream = new FileOutputStream(file);
                } catch (FileNotFoundException e2) {
                    throw new RuntimeException(e2);
                }
            }
            urlRequest.read(ByteBuffer.allocateDirect(65536));
        }

        public void onSucceeded(UrlRequest urlRequest, UrlResponseInfo urlResponseInfo) {
            int i;
            byte[] bArr;
            OutputStream outputStream;
            Log.d("HttpConnection", "[onSucceeded] Request completed in: " + ((System.nanoTime() - this.startTimeNanos) / 1000000) + "ms.");
            HashMap hashMap = new HashMap();
            for (Map.Entry next : urlResponseInfo.getAllHeaders().entrySet()) {
                List list = (List) next.getValue();
                if (!list.isEmpty() && next.getKey() != null) {
                    hashMap.put((String) next.getKey(), (String) list.iterator().next());
                }
            }
            if (this.saveFilePath == null || (outputStream = this.fileOutputStream) == null) {
                bArr = this.bytesReceived.toByteArray();
                i = bArr.length;
            } else {
                i = 0;
                try {
                    outputStream.write(this.bytesReceived.toByteArray(), 0, this.bytesReceived.size());
                    this.fileOutputStream.flush();
                    this.fileOutputStream.close();
                } catch (IOException e) {
                    Log.e("HttpConnection", e.toString());
                }
                bArr = null;
            }
            if (HttpConnection.this.jniCall) {
                HttpConnection.downloadComplete(HttpConnection.this.connectionID.intValue(), urlResponseInfo.getHttpStatusCode(), hashMap, i, bArr);
            }
            if (HttpConnection.this.listener != null) {
                HttpConnection.this.listener.downloadComplete(HttpConnection.this.connectionID.intValue(), i, bArr);
            }
        }
    }

    static {
        int availableProcessors = Runtime.getRuntime().availableProcessors();
        CPU_COUNT = availableProcessors;
        int i = availableProcessors + 1;
        CORE_POOL_SIZE = i;
        int i2 = (availableProcessors * 2) + 1;
        MAXIMUM_POOL_SIZE = i2;
        LinkedBlockingQueue linkedBlockingQueue = new LinkedBlockingQueue();
        sPoolWorkQueue = linkedBlockingQueue;
        THREAD_POOL_EXECUTOR = new ThreadPoolExecutor(i, i2, 1, TimeUnit.SECONDS, linkedBlockingQueue);
    }

    public HttpConnection(String str, String str2) {
        this(str, str2, "", (HttpConnectionListener) null, (int) defaultTimeout, (HashMap<String, String>) new HashMap());
    }

    public HttpConnection(String str, String str2, HttpConnectionListener httpConnectionListener) {
        this(str, str2, "", httpConnectionListener, (int) defaultTimeout, (HashMap<String, String>) new HashMap());
    }

    public HttpConnection(String str, String str2, String str3) {
        this(str, str2, str3, (HttpConnectionListener) null, (int) defaultTimeout, (HashMap<String, String>) new HashMap());
    }

    public HttpConnection(String str, String str2, String str3, HttpConnectionListener httpConnectionListener) {
        this(str, str2, str3, httpConnectionListener, (int) defaultTimeout, (HashMap<String, String>) new HashMap());
    }

    public HttpConnection(String str, String str2, String str3, HttpConnectionListener httpConnectionListener, int i) {
        this(str, str2, str3, httpConnectionListener, i, (HashMap<String, String>) new HashMap());
    }

    public HttpConnection(String str, String str2, String str3, HttpConnectionListener httpConnectionListener, int i, HashMap<String, String> hashMap) {
        this(str, str2, str3, httpConnectionListener, i, hashMap, defaultHttpVersion);
    }

    public HttpConnection(String str, String str2, String str3, HttpConnectionListener httpConnectionListener, int i, HashMap<String, String> hashMap, HttpVersion httpVersion2) {
        this(str, str2, new byte[0], httpConnectionListener, i, hashMap, httpVersion2);
        String str4 = str3;
        try {
            try {
                this.urlBody = str3.getBytes("UTF-8");
            } catch (UnsupportedEncodingException e) {
                e = e;
            }
        } catch (UnsupportedEncodingException e2) {
            e = e2;
            Log.e("HttpConnection", "Failed to encode URL body." + e);
        }
    }

    public HttpConnection(String str, String str2, byte[] bArr, HttpConnectionListener httpConnectionListener, int i, HashMap<String, String> hashMap) {
        this(str, str2, bArr, httpConnectionListener, i, hashMap, defaultHttpVersion);
    }

    public HttpConnection(String str, String str2, byte[] bArr, HttpConnectionListener httpConnectionListener, int i, HashMap<String, String> hashMap, HttpVersion httpVersion2) {
        this.jniCall = false;
        this.httpCommand = "GET";
        this.urlBody = new byte[0];
        this.saveFilepath = null;
        this.canceled = false;
        this.errorCode = -1;
        this.httpVersion = HttpVersion.HTTP3;
        this.connectionID = HttpConnectionsManager.sharedInstance().addConnection(this);
        this.urlString = str;
        this.httpCommand = str2;
        this.urlBody = bArr;
        this.listener = httpConnectionListener;
        this.timeout = i;
        this.headerProperties = hashMap;
        this.httpVersion = httpVersion2;
        Activity activity = Miniclip.getActivity();
        if (activity != null && cronetEngine == null && CronetProviderInstaller.isInstalled()) {
            try {
                CronetEngine.Builder builder = new CronetEngine.Builder(activity.getApplicationContext());
                builder.enableHttp2(this.httpVersion.shouldEnableHTTP2()).enableQuic(this.httpVersion.shouldEnableHTTP3()).enableBrotli(false);
                cronetEngine = builder.build();
                cronetCallbackExecutorService = Executors.newFixedThreadPool(4);
            } catch (Throwable th) {
                Log.e("HttpConnection", "Failed to install Cronet Provider: " + th);
                cronetEngine = null;
            }
        }
    }

    private static boolean cancelConnection(int i) {
        HttpConnection connection = HttpConnectionsManager.sharedInstance().getConnection(Integer.valueOf(i));
        if (connection == null) {
            return false;
        }
        return connection.cancel();
    }

    /* access modifiers changed from: private */
    public static native void downloadComplete(int i, int i2, HashMap<String, String> hashMap, int i3, byte[] bArr);

    /* access modifiers changed from: private */
    public static native void downloadFailed(int i, String str, int i2);

    private static int newConnection(String str, String str2, String str3, int i, HashMap<String, String> hashMap, int i2) {
        return new HttpConnection(str, str2, str3, (HttpConnectionListener) null, i, hashMap, HttpVersion.values()[i2]).connectionID.intValue();
    }

    private static int newConnection(String str, String str2, String str3, int i, HashMap<String, String> hashMap, String str4, int i2) {
        HttpConnection httpConnection = new HttpConnection(str, str2, str3, (HttpConnectionListener) null, i, hashMap, HttpVersion.values()[i2]);
        httpConnection.saveFilepath = str4;
        return httpConnection.connectionID.intValue();
    }

    private static int newConnection(String str, String str2, byte[] bArr, int i, HashMap<String, String> hashMap, int i2) {
        return new HttpConnection(str, str2, bArr, (HttpConnectionListener) null, i, hashMap, HttpVersion.values()[i2]).connectionID.intValue();
    }

    public static void setDefaultHttpVersion(int i) {
        defaultHttpVersion = HttpVersion.values()[i];
    }

    private static boolean startConnection(int i) {
        HttpConnection connection = HttpConnectionsManager.sharedInstance().getConnection(Integer.valueOf(i));
        if (connection == null) {
            Log.w("HttpConnection", "Failed to start connection: could not find connection with ID " + i + "!");
            return false;
        }
        connection.jniCall = true;
        return connection.start();
    }

    public boolean cancel() {
        this.canceled = true;
        HttpConnectionsManager.sharedInstance().removeConnection(this.connectionID);
        return true;
    }

    public Integer getConnectionID() {
        return this.connectionID;
    }

    /* access modifiers changed from: package-private */
    /* renamed from: lambda$start$0$com-miniclip-network-HttpConnection  reason: not valid java name */
    public /* synthetic */ void m4778lambda$start$0$comminiclipnetworkHttpConnection() {
        MCHttpURLConnectionTask mCHttpURLConnectionTask = new MCHttpURLConnectionTask();
        String str = this.saveFilepath;
        if (str != null) {
            mCHttpURLConnectionTask.outputFilepath = str;
        }
        if (!this.canceled) {
            mCHttpURLConnectionTask.onPostExecute(mCHttpURLConnectionTask.doInBackground(this.urlString));
        }
    }

    public boolean start() {
        if (this.canceled) {
            Log.w("HttpConnection", "Not starting connection with ID " + this.connectionID + " - cancel was already called.");
            return false;
        } else if (this.httpVersion == HttpVersion.HTTP1_1 || cronetEngine == null) {
            THREAD_POOL_EXECUTOR.execute(new HttpConnection$$ExternalSyntheticLambda0(this));
            return true;
        } else {
            UrlRequest.Builder priority = cronetEngine.newUrlRequestBuilder(this.urlString, new UrlRequestCallback(this.saveFilepath), cronetCallbackExecutorService).setHttpMethod(this.httpCommand).setPriority(0);
            if (this.httpCommand.equals("POST") || this.httpCommand.equals("PUT")) {
                priority.addHeader("Content-Type", ShareTarget.ENCODING_TYPE_URL_ENCODED);
                priority.addHeader("Content-Length", Integer.toString(this.urlBody.length));
                priority.setUploadDataProvider(new PostDataProvider(this.urlBody), cronetCallbackExecutorService);
            }
            for (Map.Entry next : this.headerProperties.entrySet()) {
                priority.addHeader((String) next.getKey(), (String) next.getValue());
            }
            priority.build().start();
            return true;
        }
    }
}
