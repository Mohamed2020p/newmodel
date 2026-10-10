package com.miniclip.network;

import android.util.Log;
import java.io.ByteArrayOutputStream;
import java.io.DataOutputStream;
import java.io.IOException;
import java.net.DatagramPacket;
import java.net.DatagramSocket;
import java.net.InetAddress;
import java.net.Socket;
import java.nio.ByteBuffer;
import java.util.ArrayList;
import java.util.Collections;
import java.util.Iterator;
import java.util.List;
import kotlin.UShort;

public class JavaSocket {
    private static final String TrafficTypeInbound = "inbound";
    private static final String TrafficTypeOutbound = "outbound";
    /* access modifiers changed from: private */
    public static boolean _certificateCheckEnabled = true;
    /* access modifiers changed from: private */
    public static ArrayList<byte[]> _certs;
    /* access modifiers changed from: private */
    public DatagramSocket _datagramSocket = null;
    /* access modifiers changed from: private */
    public DataOutputStream _dout = null;
    /* access modifiers changed from: private */
    public InetAddress _host = null;
    /* access modifiers changed from: private */
    public String _hostAddress;
    /* access modifiers changed from: private */
    public int _hostPort;
    /* access modifiers changed from: private */
    public long _nativeObject;
    private ByteArrayOutputStream _packetBuffer = new ByteArrayOutputStream();
    private int _packetSize;
    private PacketSizeHeaderType _packetSizeHeaderType;
    private List<byte[]> _queuedData = Collections.synchronizedList(new ArrayList());
    /* access modifiers changed from: private */
    public Thread _sendThread;
    Socket _socket = null;
    /* access modifiers changed from: private */
    public SocketType _socketType;
    /* access modifiers changed from: private */
    public State _state = State.NotConnected;
    /* access modifiers changed from: private */
    public int _workBufferSize;

    /* renamed from: com.miniclip.network.JavaSocket$4  reason: invalid class name */
    static /* synthetic */ class AnonymousClass4 {
        static final /* synthetic */ int[] $SwitchMap$com$miniclip$network$JavaSocket$PacketSizeHeaderType;

        /* JADX WARNING: Can't wrap try/catch for region: R(8:0|1|2|3|4|5|6|(3:7|8|10)) */
        /* JADX WARNING: Failed to process nested try/catch */
        /* JADX WARNING: Missing exception handler attribute for start block: B:3:0x0012 */
        /* JADX WARNING: Missing exception handler attribute for start block: B:5:0x001d */
        /* JADX WARNING: Missing exception handler attribute for start block: B:7:0x0028 */
        static {
            int[] iArr = new int[PacketSizeHeaderType.values().length];
            $SwitchMap$com$miniclip$network$JavaSocket$PacketSizeHeaderType = iArr;
            iArr[PacketSizeHeaderType.Size16.ordinal()] = 1;
            $SwitchMap$com$miniclip$network$JavaSocket$PacketSizeHeaderType[PacketSizeHeaderType.Size32.ordinal()] = 2;
            $SwitchMap$com$miniclip$network$JavaSocket$PacketSizeHeaderType[PacketSizeHeaderType.Size64.ordinal()] = 3;
            try {
                $SwitchMap$com$miniclip$network$JavaSocket$PacketSizeHeaderType[PacketSizeHeaderType.None.ordinal()] = 4;
            } catch (NoSuchFieldError unused) {
            }
        }
    }

    private enum PacketSizeHeaderType {
        None,
        Size16,
        Size32,
        Size64
    }

    private enum SocketType {
        TCP,
        TCPSSL,
        UDP
    }

    private enum State {
        NotConnected,
        Connecting,
        Connected,
        Closed
    }

    /* JADX WARNING: Can't fix incorrect switch cases order */
    /* JADX WARNING: Code restructure failed: missing block: B:31:0x00c2, code lost:
        if (r2.equals("size16") == false) goto L_0x0096;
     */
    JavaSocket(String str, String str2, int i, long j, int i2, String str3) {
        boolean z;
        this._hostAddress = str2;
        this._hostPort = i;
        this._nativeObject = j;
        this._workBufferSize = i2;
        char c = 0;
        this._packetSize = 0;
        String lowerCase = str.toLowerCase();
        lowerCase.hashCode();
        switch (lowerCase.hashCode()) {
            case -879106421:
                if (lowerCase.equals("tcpssl")) {
                    z = false;
                    break;
                }
            case 114657:
                if (lowerCase.equals("tcp")) {
                    z = true;
                    break;
                }
            case 115649:
                if (lowerCase.equals("udp")) {
                    z = true;
                    break;
                }
            default:
                z = true;
                break;
        }
        switch (z) {
            case false:
                this._socketType = SocketType.TCPSSL;
                break;
            case true:
                this._socketType = SocketType.TCP;
                break;
            case true:
                this._socketType = SocketType.UDP;
                break;
            default:
                throw new IllegalArgumentException("Invalid Socket Type: " + str);
        }
        String lowerCase2 = str3.toLowerCase();
        lowerCase2.hashCode();
        switch (lowerCase2.hashCode()) {
            case -901912090:
                break;
            case -901912032:
                if (lowerCase2.equals("size32")) {
                    c = 1;
                    break;
                }
            case -901911937:
                if (lowerCase2.equals("size64")) {
                    c = 2;
                    break;
                }
            case 3387192:
                if (lowerCase2.equals("none")) {
                    c = 3;
                    break;
                }
            default:
                c = 65535;
                break;
        }
        switch (c) {
            case 0:
                this._packetSizeHeaderType = PacketSizeHeaderType.Size16;
                break;
            case 1:
                this._packetSizeHeaderType = PacketSizeHeaderType.Size32;
                break;
            case 2:
                this._packetSizeHeaderType = PacketSizeHeaderType.Size64;
                break;
            case 3:
                this._packetSizeHeaderType = PacketSizeHeaderType.None;
                break;
            default:
                throw new IllegalArgumentException("Invalid Packet Size Type: " + str3);
        }
        this._sendThread = new Thread(new Runnable() {
            public void run() {
                while (JavaSocket.this.isConnected()) {
                    JavaSocket.this.sendQueuedData();
                    synchronized (JavaSocket.this._sendThread) {
                        try {
                            JavaSocket.this._sendThread.wait(1000);
                        } catch (Exception e) {
                            e.printStackTrace();
                        }
                    }
                }
            }
        });
    }

    public static boolean addTrustedCertificate(byte[] bArr) {
        if (_certs == null) {
            _certs = new ArrayList<>();
        }
        _certs.add(bArr);
        return true;
    }

    public static void clearTrustedCertificates() {
        if (_certs == null) {
            _certs = new ArrayList<>();
        }
        _certs.clear();
    }

    private boolean connectTCP() {
        new Thread(new Runnable() {
            /*  JADX ERROR: IndexOutOfBoundsException in pass: RegionMakerVisitor
                java.lang.IndexOutOfBoundsException: Index 0 out of bounds for length 0
                	at java.base/jdk.internal.util.Preconditions.outOfBounds(Unknown Source)
                	at java.base/jdk.internal.util.Preconditions.outOfBoundsCheckIndex(Unknown Source)
                	at java.base/jdk.internal.util.Preconditions.checkIndex(Unknown Source)
                	at java.base/java.util.Objects.checkIndex(Unknown Source)
                	at java.base/java.util.ArrayList.get(Unknown Source)
                	at jadx.core.dex.nodes.InsnNode.getArg(InsnNode.java:101)
                	at jadx.core.dex.visitors.regions.RegionMaker.traverseMonitorExits(RegionMaker.java:611)
                	at jadx.core.dex.visitors.regions.RegionMaker.traverseMonitorExits(RegionMaker.java:619)
                	at jadx.core.dex.visitors.regions.RegionMaker.traverseMonitorExits(RegionMaker.java:619)
                	at jadx.core.dex.visitors.regions.RegionMaker.traverseMonitorExits(RegionMaker.java:619)
                	at jadx.core.dex.visitors.regions.RegionMaker.processMonitorEnter(RegionMaker.java:561)
                	at jadx.core.dex.visitors.regions.RegionMaker.traverse(RegionMaker.java:133)
                	at jadx.core.dex.visitors.regions.RegionMaker.makeRegion(RegionMaker.java:86)
                	at jadx.core.dex.visitors.regions.RegionMaker.processExcHandler(RegionMaker.java:1043)
                	at jadx.core.dex.visitors.regions.RegionMaker.processTryCatchBlocks(RegionMaker.java:975)
                	at jadx.core.dex.visitors.regions.RegionMakerVisitor.visit(RegionMakerVisitor.java:52)
                */
            public void run() {
                /*
                    r11 = this;
                    com.miniclip.network.JavaSocket r0 = com.miniclip.network.JavaSocket.this
                    com.miniclip.network.JavaSocket$State r0 = r0._state
                    monitor-enter(r0)
                    com.miniclip.network.JavaSocket r1 = com.miniclip.network.JavaSocket.this     // Catch:{ all -> 0x01ad }
                    com.miniclip.network.JavaSocket$State r1 = r1._state     // Catch:{ all -> 0x01ad }
                    com.miniclip.network.JavaSocket$State r2 = com.miniclip.network.JavaSocket.State.NotConnected     // Catch:{ all -> 0x01ad }
                    if (r1 == r2) goto L_0x0013
                    monitor-exit(r0)     // Catch:{ all -> 0x01ad }
                    return
                L_0x0013:
                    com.miniclip.network.JavaSocket r1 = com.miniclip.network.JavaSocket.this     // Catch:{ all -> 0x01ad }
                    com.miniclip.network.JavaSocket$State r2 = com.miniclip.network.JavaSocket.State.Connecting     // Catch:{ all -> 0x01ad }
                    com.miniclip.network.JavaSocket.State unused = r1._state = r2     // Catch:{ all -> 0x01ad }
                    monitor-exit(r0)     // Catch:{ all -> 0x01ad }
                    r0 = 1
                    r1 = 0
                    r2 = 0
                    com.miniclip.network.JavaSocket r3 = com.miniclip.network.JavaSocket.this     // Catch:{ Exception -> 0x00f3 }
                    java.lang.String r3 = r3._hostAddress     // Catch:{ Exception -> 0x00f3 }
                    java.lang.String r4 = ":"
                    boolean r3 = r3.contains(r4)     // Catch:{ Exception -> 0x00f3 }
                    if (r3 == 0) goto L_0x003a
                    com.miniclip.network.JavaSocket r3 = com.miniclip.network.JavaSocket.this     // Catch:{ Exception -> 0x00f3 }
                    java.lang.String r4 = r3._hostAddress     // Catch:{ Exception -> 0x00f3 }
                    java.net.InetAddress r4 = java.net.Inet6Address.getByName(r4)     // Catch:{ Exception -> 0x00f3 }
                    java.net.InetAddress unused = r3._host = r4     // Catch:{ Exception -> 0x00f3 }
                    goto L_0x0047
                L_0x003a:
                    com.miniclip.network.JavaSocket r3 = com.miniclip.network.JavaSocket.this     // Catch:{ Exception -> 0x00f3 }
                    java.lang.String r4 = r3._hostAddress     // Catch:{ Exception -> 0x00f3 }
                    java.net.InetAddress r4 = java.net.InetAddress.getByName(r4)     // Catch:{ Exception -> 0x00f3 }
                    java.net.InetAddress unused = r3._host = r4     // Catch:{ Exception -> 0x00f3 }
                L_0x0047:
                    com.miniclip.network.JavaSocket r3 = com.miniclip.network.JavaSocket.this     // Catch:{ Exception -> 0x00f3 }
                    java.lang.String r3 = r3._hostAddress     // Catch:{ Exception -> 0x00f3 }
                    java.net.InetAddress.getAllByName(r3)     // Catch:{ Exception -> 0x00f3 }
                    com.miniclip.network.JavaSocket r3 = com.miniclip.network.JavaSocket.this     // Catch:{ Exception -> 0x00f3 }
                    com.miniclip.network.JavaSocket$SocketType r3 = r3._socketType     // Catch:{ Exception -> 0x00f3 }
                    com.miniclip.network.JavaSocket$SocketType r4 = com.miniclip.network.JavaSocket.SocketType.TCPSSL     // Catch:{ Exception -> 0x00f3 }
                    if (r3 != r4) goto L_0x00be
                    java.lang.String r3 = "TLS"
                    javax.net.ssl.SSLContext r3 = javax.net.ssl.SSLContext.getInstance(r3)     // Catch:{ Exception -> 0x00f3 }
                    boolean r4 = com.miniclip.network.JavaSocket._certificateCheckEnabled     // Catch:{ Exception -> 0x00f3 }
                    if (r4 != 0) goto L_0x0079
                    java.lang.String r3 = "TLSv1.2"
                    javax.net.ssl.SSLContext r3 = javax.net.ssl.SSLContext.getInstance(r3)     // Catch:{ Exception -> 0x00f3 }
                    javax.net.ssl.TrustManager[] r4 = new javax.net.ssl.TrustManager[r0]     // Catch:{ Exception -> 0x00f3 }
                    com.miniclip.network.JavaTrustManager r5 = new com.miniclip.network.JavaTrustManager     // Catch:{ Exception -> 0x00f3 }
                    r5.<init>()     // Catch:{ Exception -> 0x00f3 }
                    r4[r1] = r5     // Catch:{ Exception -> 0x00f3 }
                    r3.init(r2, r4, r2)     // Catch:{ Exception -> 0x00f3 }
                    goto L_0x009d
                L_0x0079:
                    java.util.ArrayList r4 = com.miniclip.network.JavaSocket._certs     // Catch:{ Exception -> 0x00f3 }
                    if (r4 == 0) goto L_0x009a
                    java.util.ArrayList r4 = com.miniclip.network.JavaSocket._certs     // Catch:{ Exception -> 0x00f3 }
                    int r4 = r4.size()     // Catch:{ Exception -> 0x00f3 }
                    if (r4 <= 0) goto L_0x009a
                    javax.net.ssl.TrustManager[] r4 = new javax.net.ssl.TrustManager[r0]     // Catch:{ Exception -> 0x00f3 }
                    com.miniclip.network.JavaTrustManager r5 = new com.miniclip.network.JavaTrustManager     // Catch:{ Exception -> 0x00f3 }
                    java.util.ArrayList r6 = com.miniclip.network.JavaSocket._certs     // Catch:{ Exception -> 0x00f3 }
                    r5.<init>(r6)     // Catch:{ Exception -> 0x00f3 }
                    r4[r1] = r5     // Catch:{ Exception -> 0x00f3 }
                    r3.init(r2, r4, r2)     // Catch:{ Exception -> 0x00f3 }
                    goto L_0x009d
                L_0x009a:
                    r3.init(r2, r2, r2)     // Catch:{ Exception -> 0x00f3 }
                L_0x009d:
                    javax.net.ssl.SSLSocketFactory r3 = r3.getSocketFactory()     // Catch:{ Exception -> 0x00f3 }
                    com.miniclip.network.JavaSocket r4 = com.miniclip.network.JavaSocket.this     // Catch:{ Exception -> 0x00f3 }
                    java.net.InetAddress r4 = r4._host     // Catch:{ Exception -> 0x00f3 }
                    com.miniclip.network.JavaSocket r5 = com.miniclip.network.JavaSocket.this     // Catch:{ Exception -> 0x00f3 }
                    int r5 = r5._hostPort     // Catch:{ Exception -> 0x00f3 }
                    java.net.Socket r3 = r3.createSocket(r4, r5)     // Catch:{ Exception -> 0x00f3 }
                    javax.net.ssl.SSLSocket r3 = (javax.net.ssl.SSLSocket) r3     // Catch:{ Exception -> 0x00f3 }
                    r3.setUseClientMode(r0)     // Catch:{ Exception -> 0x00f3 }
                    r3.startHandshake()     // Catch:{ Exception -> 0x00f3 }
                    com.miniclip.network.JavaSocket r4 = com.miniclip.network.JavaSocket.this     // Catch:{ Exception -> 0x00f3 }
                    r4._socket = r3     // Catch:{ Exception -> 0x00f3 }
                    goto L_0x00dd
                L_0x00be:
                    com.miniclip.network.JavaSocket r3 = com.miniclip.network.JavaSocket.this     // Catch:{ Exception -> 0x00f3 }
                    com.miniclip.network.JavaSocket$SocketType r3 = r3._socketType     // Catch:{ Exception -> 0x00f3 }
                    com.miniclip.network.JavaSocket$SocketType r4 = com.miniclip.network.JavaSocket.SocketType.TCP     // Catch:{ Exception -> 0x00f3 }
                    if (r3 != r4) goto L_0x00dd
                    com.miniclip.network.JavaSocket r3 = com.miniclip.network.JavaSocket.this     // Catch:{ Exception -> 0x00f3 }
                    java.net.Socket r4 = new java.net.Socket     // Catch:{ Exception -> 0x00f3 }
                    com.miniclip.network.JavaSocket r5 = com.miniclip.network.JavaSocket.this     // Catch:{ Exception -> 0x00f3 }
                    java.net.InetAddress r5 = r5._host     // Catch:{ Exception -> 0x00f3 }
                    com.miniclip.network.JavaSocket r6 = com.miniclip.network.JavaSocket.this     // Catch:{ Exception -> 0x00f3 }
                    int r6 = r6._hostPort     // Catch:{ Exception -> 0x00f3 }
                    r4.<init>(r5, r6)     // Catch:{ Exception -> 0x00f3 }
                    r3._socket = r4     // Catch:{ Exception -> 0x00f3 }
                L_0x00dd:
                    com.miniclip.network.JavaSocket r3 = com.miniclip.network.JavaSocket.this     // Catch:{ Exception -> 0x00f3 }
                    java.net.Socket r3 = r3._socket     // Catch:{ Exception -> 0x00f3 }
                    java.io.InputStream r3 = r3.getInputStream()     // Catch:{ Exception -> 0x00f3 }
                    com.miniclip.network.JavaSocket r4 = com.miniclip.network.JavaSocket.this     // Catch:{ Exception -> 0x00ee }
                    java.net.Socket r4 = r4._socket     // Catch:{ Exception -> 0x00ee }
                    java.io.OutputStream r4 = r4.getOutputStream()     // Catch:{ Exception -> 0x00ee }
                    goto L_0x0100
                L_0x00ee:
                    r4 = move-exception
                    r10 = r4
                    r4 = r3
                    r3 = r10
                    goto L_0x00f5
                L_0x00f3:
                    r3 = move-exception
                    r4 = r2
                L_0x00f5:
                    r3.printStackTrace()
                    java.lang.String r3 = r3.getMessage()
                    r10 = r4
                    r4 = r2
                    r2 = r3
                    r3 = r10
                L_0x0100:
                    r5 = -1
                    if (r2 != 0) goto L_0x019f
                    com.miniclip.network.JavaSocket r6 = com.miniclip.network.JavaSocket.this
                    java.net.Socket r6 = r6._socket
                    if (r6 == 0) goto L_0x019f
                    java.io.DataInputStream r6 = new java.io.DataInputStream
                    r6.<init>(r3)
                    com.miniclip.network.JavaSocket r3 = com.miniclip.network.JavaSocket.this
                    java.io.DataOutputStream r7 = new java.io.DataOutputStream
                    r7.<init>(r4)
                    java.io.DataOutputStream unused = r3._dout = r7
                    com.miniclip.network.JavaSocket r3 = com.miniclip.network.JavaSocket.this
                    com.miniclip.network.JavaSocket$State r3 = r3._state
                    monitor-enter(r3)
                    com.miniclip.network.JavaSocket r4 = com.miniclip.network.JavaSocket.this     // Catch:{ all -> 0x019c }
                    com.miniclip.network.JavaSocket$State r7 = com.miniclip.network.JavaSocket.State.Connected     // Catch:{ all -> 0x019c }
                    com.miniclip.network.JavaSocket.State unused = r4._state = r7     // Catch:{ all -> 0x019c }
                    monitor-exit(r3)     // Catch:{ all -> 0x019c }
                    com.miniclip.network.JavaSocket r3 = com.miniclip.network.JavaSocket.this
                    long r3 = r3._nativeObject
                    com.miniclip.network.JavaSocket.onConnect(r3)
                    com.miniclip.network.JavaSocket r3 = com.miniclip.network.JavaSocket.this
                    java.lang.Thread r3 = r3._sendThread
                    r3.start()
                    com.miniclip.network.JavaSocket r3 = com.miniclip.network.JavaSocket.this     // Catch:{ Exception -> 0x018b }
                    int r3 = r3._workBufferSize     // Catch:{ Exception -> 0x018b }
                    byte[] r3 = new byte[r3]     // Catch:{ Exception -> 0x018b }
                L_0x0141:
                    com.miniclip.network.JavaSocket r4 = com.miniclip.network.JavaSocket.this     // Catch:{ Exception -> 0x018b }
                    boolean r4 = r4.isConnected()     // Catch:{ Exception -> 0x018b }
                    if (r4 == 0) goto L_0x019f
                    int r4 = r6.read(r3)     // Catch:{ Exception -> 0x018b }
                    if (r4 != r5) goto L_0x0150
                    goto L_0x019f
                L_0x0150:
                    int r7 = r6.available()     // Catch:{ Exception -> 0x018b }
                    if (r7 <= 0) goto L_0x017f
                    java.io.ByteArrayOutputStream r7 = new java.io.ByteArrayOutputStream     // Catch:{ Exception -> 0x018b }
                    r7.<init>()     // Catch:{ Exception -> 0x018b }
                    r7.write(r3, r1, r4)     // Catch:{ Exception -> 0x018b }
                L_0x015e:
                    int r4 = r6.available()     // Catch:{ Exception -> 0x018b }
                    if (r4 <= 0) goto L_0x0172
                    int r4 = r6.read(r3)     // Catch:{ Exception -> 0x018b }
                    if (r4 <= 0) goto L_0x016e
                    r7.write(r3, r1, r4)     // Catch:{ Exception -> 0x018b }
                    goto L_0x015e
                L_0x016e:
                    if (r4 != r5) goto L_0x015e
                    r4 = r0
                    goto L_0x0173
                L_0x0172:
                    r4 = r1
                L_0x0173:
                    byte[] r8 = r7.toByteArray()     // Catch:{ Exception -> 0x018b }
                    int r7 = r7.size()     // Catch:{ Exception -> 0x018b }
                    r10 = r7
                    r7 = r4
                    r4 = r10
                    goto L_0x0181
                L_0x017f:
                    r7 = r1
                    r8 = r3
                L_0x0181:
                    if (r4 <= 0) goto L_0x0188
                    com.miniclip.network.JavaSocket r9 = com.miniclip.network.JavaSocket.this     // Catch:{ Exception -> 0x018b }
                    r9.onDataPartial(r4, r8)     // Catch:{ Exception -> 0x018b }
                L_0x0188:
                    if (r7 == 0) goto L_0x0141
                    goto L_0x019f
                L_0x018b:
                    r0 = move-exception
                    com.miniclip.network.JavaSocket r1 = com.miniclip.network.JavaSocket.this
                    boolean r1 = r1.isClosed()
                    if (r1 != 0) goto L_0x019f
                    r0.printStackTrace()
                    java.lang.String r2 = r0.getMessage()
                    goto L_0x019f
                L_0x019c:
                    r0 = move-exception
                    monitor-exit(r3)     // Catch:{ all -> 0x019c }
                    throw r0
                L_0x019f:
                    if (r2 == 0) goto L_0x01a7
                    com.miniclip.network.JavaSocket r0 = com.miniclip.network.JavaSocket.this
                    r0.cleanup(r5, r2)
                    goto L_0x01ac
                L_0x01a7:
                    com.miniclip.network.JavaSocket r0 = com.miniclip.network.JavaSocket.this
                    r0.cleanup()
                L_0x01ac:
                    return
                L_0x01ad:
                    r1 = move-exception
                    monitor-exit(r0)     // Catch:{ all -> 0x01ad }
                    throw r1
                */
                throw new UnsupportedOperationException("Method not decompiled: com.miniclip.network.JavaSocket.AnonymousClass3.run():void");
            }
        }).start();
        return true;
    }

    private boolean connectUDP() {
        new Thread(new Runnable() {
            /* JADX WARNING: Code restructure failed: missing block: B:10:?, code lost:
                r0 = r5.this$0;
                com.miniclip.network.JavaSocket.access$302(r0, java.net.InetAddress.getByName(com.miniclip.network.JavaSocket.access$400(r0)));
                com.miniclip.network.JavaSocket.access$502(r5.this$0, new java.net.DatagramSocket());
                r0 = com.miniclip.network.JavaSocket.access$600(r5.this$0);
                r1 = new byte[r0];
                r2 = com.miniclip.network.JavaSocket.access$200(r5.this$0);
             */
            /* JADX WARNING: Code restructure failed: missing block: B:11:0x0040, code lost:
                monitor-enter(r2);
             */
            /* JADX WARNING: Code restructure failed: missing block: B:13:?, code lost:
                com.miniclip.network.JavaSocket.access$202(r5.this$0, com.miniclip.network.JavaSocket.State.Connected);
             */
            /* JADX WARNING: Code restructure failed: missing block: B:14:0x0048, code lost:
                monitor-exit(r2);
             */
            /* JADX WARNING: Code restructure failed: missing block: B:16:?, code lost:
                com.miniclip.network.JavaSocket.onConnect(com.miniclip.network.JavaSocket.access$700(r5.this$0));
                com.miniclip.network.JavaSocket.access$100(r5.this$0).start();
             */
            /* JADX WARNING: Code restructure failed: missing block: B:19:0x0061, code lost:
                if (r5.this$0.isConnected() == false) goto L_0x00b2;
             */
            /* JADX WARNING: Code restructure failed: missing block: B:20:0x0063, code lost:
                com.miniclip.network.JavaSocket.access$500(r5.this$0).setSoTimeout(2000);
                r2 = new java.net.DatagramPacket(r1, r0, com.miniclip.network.JavaSocket.access$300(r5.this$0), com.miniclip.network.JavaSocket.access$800(r5.this$0));
             */
            /* JADX WARNING: Code restructure failed: missing block: B:22:?, code lost:
                com.miniclip.network.JavaSocket.access$500(r5.this$0).receive(r2);
             */
            /* JADX WARNING: Code restructure failed: missing block: B:23:0x008e, code lost:
                if (r5.this$0.isConnected() == false) goto L_0x005b;
             */
            /* JADX WARNING: Code restructure failed: missing block: B:24:0x0090, code lost:
                r5.this$0.onDataPartial(r2.getLength(), r2.getData());
             */
            /* JADX WARNING: Code restructure failed: missing block: B:30:0x00a1, code lost:
                r0 = move-exception;
             */
            /* JADX WARNING: Code restructure failed: missing block: B:32:0x00a8, code lost:
                if (r5.this$0.isClosed() == false) goto L_0x00aa;
             */
            /* JADX WARNING: Code restructure failed: missing block: B:33:0x00aa, code lost:
                r0.printStackTrace();
                r0 = r0.getMessage();
             */
            /* JADX WARNING: Code restructure failed: missing block: B:34:0x00b2, code lost:
                r0 = null;
             */
            /* JADX WARNING: Code restructure failed: missing block: B:35:0x00b3, code lost:
                if (r0 != null) goto L_0x00b5;
             */
            /* JADX WARNING: Code restructure failed: missing block: B:36:0x00b5, code lost:
                r5.this$0.cleanup(-1, r0);
             */
            /* JADX WARNING: Code restructure failed: missing block: B:37:0x00bc, code lost:
                r5.this$0.cleanup();
             */
            /* JADX WARNING: Code restructure failed: missing block: B:51:?, code lost:
                return;
             */
            /* JADX WARNING: Code restructure failed: missing block: B:52:?, code lost:
                return;
             */
            public void run() {
                synchronized (JavaSocket.this._state) {
                    if (JavaSocket.this._state == State.NotConnected) {
                        State unused = JavaSocket.this._state = State.Connecting;
                    }
                }
            }
        }).start();
        return true;
    }

    public static native void onConnect(long j);

    public static native void onData(long j, byte[] bArr, int i, int i2);

    public static native void onDisconnect(long j, int i, String str);

    public static native void onTrafficError(long j, String str, int i, String str2);

    private boolean sendDataTCP(byte[] bArr) {
        DataOutputStream dataOutputStream = this._dout;
        if (dataOutputStream != null) {
            try {
                dataOutputStream.write(bArr, 0, bArr.length);
                return true;
            } catch (IOException e) {
                if (!isClosed()) {
                    onTrafficError(this._nativeObject, TrafficTypeOutbound, -1, e.toString());
                    e.printStackTrace();
                }
            }
        }
        return false;
    }

    private boolean sendDataUDP(byte[] bArr) {
        if (this._datagramSocket == null) {
            return false;
        }
        try {
            this._datagramSocket.send(new DatagramPacket(bArr, bArr.length, this._host, this._hostPort));
            return true;
        } catch (Exception e) {
            if (isClosed()) {
                return false;
            }
            onTrafficError(this._nativeObject, TrafficTypeOutbound, -1, e.toString());
            e.printStackTrace();
            return false;
        }
    }

    /* access modifiers changed from: private */
    public void sendQueuedData() {
        synchronized (this._queuedData) {
            Iterator<byte[]> it = this._queuedData.iterator();
            while (it.hasNext()) {
                if (this._socketType == SocketType.UDP) {
                    sendDataUDP(it.next());
                } else {
                    sendDataTCP(it.next());
                }
            }
            this._queuedData.clear();
        }
    }

    public static void setCertificateCheckEnabled(boolean z) {
        _certificateCheckEnabled = z;
    }

    public void cleanup() {
        cleanup(0, "");
    }

    /* JADX WARNING: Code restructure failed: missing block: B:11:0x001f, code lost:
        if (r5._socketType != com.miniclip.network.JavaSocket.SocketType.UDP) goto L_0x0025;
     */
    /* JADX WARNING: Code restructure failed: missing block: B:12:0x0021, code lost:
        cleanupUdp();
     */
    /* JADX WARNING: Code restructure failed: missing block: B:13:0x0025, code lost:
        cleanupTcp();
     */
    /* JADX WARNING: Code restructure failed: missing block: B:14:0x0028, code lost:
        r3 = r5._sendThread;
     */
    /* JADX WARNING: Code restructure failed: missing block: B:15:0x002a, code lost:
        monitor-enter(r3);
     */
    /* JADX WARNING: Code restructure failed: missing block: B:17:?, code lost:
        r5._sendThread.notify();
     */
    /* JADX WARNING: Code restructure failed: missing block: B:18:0x0030, code lost:
        monitor-exit(r3);
     */
    /* JADX WARNING: Code restructure failed: missing block: B:19:0x0031, code lost:
        onDisconnect(r1, r6, r7);
     */
    /* JADX WARNING: Code restructure failed: missing block: B:20:0x0034, code lost:
        return;
     */
    public void cleanup(int i, String str) {
        synchronized (this._state) {
            if (this._state != State.Closed) {
                if (this._state != State.NotConnected) {
                    this._state = State.Closed;
                    long j = this._nativeObject;
                    this._nativeObject = 0;
                }
            }
        }
    }

    public void cleanupTcp() {
        try {
            Socket socket = this._socket;
            if (socket != null) {
                socket.close();
            }
        } catch (Exception e) {
            e.printStackTrace();
        } catch (Throwable th) {
            this._socket = null;
            throw th;
        }
        this._socket = null;
    }

    public void cleanupUdp() {
        try {
            DatagramSocket datagramSocket = this._datagramSocket;
            if (datagramSocket != null) {
                datagramSocket.close();
            }
        } catch (Exception e) {
            e.printStackTrace();
        } catch (Throwable th) {
            this._datagramSocket = null;
            throw th;
        }
        this._datagramSocket = null;
    }

    /* JADX WARNING: Code restructure failed: missing block: B:18:0x0034, code lost:
        if (r4._socketType != com.miniclip.network.JavaSocket.SocketType.UDP) goto L_0x003b;
     */
    /* JADX WARNING: Code restructure failed: missing block: B:20:0x003a, code lost:
        return connectUDP();
     */
    /* JADX WARNING: Code restructure failed: missing block: B:22:0x003f, code lost:
        return connectTCP();
     */
    public boolean connect() {
        if (this._hostAddress.isEmpty()) {
            Log.e("Socket", "Host address must not be empty");
            return false;
        } else if (this._hostPort == 0) {
            Log.e("Socket", "Host port must not be 0");
            return false;
        } else {
            synchronized (this._state) {
                if (this._state != State.NotConnected) {
                    Log.e("Socket", "connect() can only be called successfully once per instance");
                    return false;
                }
            }
        }
    }

    /* JADX WARNING: Code restructure failed: missing block: B:11:0x001a, code lost:
        cleanup();
     */
    /* JADX WARNING: Code restructure failed: missing block: B:12:0x001e, code lost:
        return true;
     */
    public boolean disconnect() {
        synchronized (this._state) {
            if (this._state != State.Connected && this._state != State.Connecting) {
                Log.e("Socket", "disconnect() can only be called on open connections");
                return false;
            }
        }
    }

    public boolean isClosed() {
        boolean z;
        synchronized (this._state) {
            z = this._state == State.Closed;
        }
        return z;
    }

    public boolean isConnected() {
        boolean z;
        synchronized (this._state) {
            z = this._state == State.Connected;
        }
        return z;
    }

    public void onDataPartial(int i, byte[] bArr) {
        if (this._packetSizeHeaderType != PacketSizeHeaderType.None) {
            int i2 = AnonymousClass4.$SwitchMap$com$miniclip$network$JavaSocket$PacketSizeHeaderType[this._packetSizeHeaderType.ordinal()];
            int i3 = i2 != 1 ? i2 != 2 ? i2 != 3 ? 0 : 8 : 4 : 2;
            this._packetBuffer.write(bArr, 0, i);
            while (true) {
                if (this._packetSize == 0 && this._packetBuffer.size() > i3) {
                    int i4 = AnonymousClass4.$SwitchMap$com$miniclip$network$JavaSocket$PacketSizeHeaderType[this._packetSizeHeaderType.ordinal()];
                    if (i4 == 1) {
                        this._packetSize = ByteBuffer.wrap(this._packetBuffer.toByteArray()).getShort() & UShort.MAX_VALUE;
                    } else if (i4 == 2) {
                        this._packetSize = ByteBuffer.wrap(this._packetBuffer.toByteArray()).getInt();
                    } else if (i4 == 3) {
                        this._packetSize = (int) ByteBuffer.wrap(this._packetBuffer.toByteArray()).getLong();
                    }
                }
                if (this._packetSize != 0 && this._packetBuffer.size() - i3 >= this._packetSize) {
                    onData(this._nativeObject, this._packetBuffer.toByteArray(), i3, this._packetSize);
                    ByteArrayOutputStream byteArrayOutputStream = new ByteArrayOutputStream();
                    byteArrayOutputStream.write(this._packetBuffer.toByteArray(), this._packetSize + i3, this._packetBuffer.size() - (this._packetSize + i3));
                    this._packetBuffer = byteArrayOutputStream;
                    this._packetSize = 0;
                } else {
                    return;
                }
            }
        } else {
            onData(this._nativeObject, bArr, 0, i);
        }
    }

    /* JADX WARNING: Code restructure failed: missing block: B:13:0x001f, code lost:
        r0 = com.miniclip.network.JavaSocket.AnonymousClass4.$SwitchMap$com$miniclip$network$JavaSocket$PacketSizeHeaderType[r5._packetSizeHeaderType.ordinal()];
     */
    /* JADX WARNING: Code restructure failed: missing block: B:14:0x002b, code lost:
        if (r0 == 1) goto L_0x004a;
     */
    /* JADX WARNING: Code restructure failed: missing block: B:15:0x002d, code lost:
        if (r0 == 2) goto L_0x0040;
     */
    /* JADX WARNING: Code restructure failed: missing block: B:17:0x0030, code lost:
        if (r0 == 3) goto L_0x0034;
     */
    /* JADX WARNING: Code restructure failed: missing block: B:18:0x0032, code lost:
        r0 = null;
     */
    /* JADX WARNING: Code restructure failed: missing block: B:19:0x0034, code lost:
        r0 = java.nio.ByteBuffer.allocate(8);
        r0.putLong((long) r6.length);
     */
    /* JADX WARNING: Code restructure failed: missing block: B:20:0x0040, code lost:
        r0 = java.nio.ByteBuffer.allocate(4);
        r0.putInt(r6.length);
     */
    /* JADX WARNING: Code restructure failed: missing block: B:22:0x004e, code lost:
        if (r6.length <= 65535) goto L_0x0058;
     */
    /* JADX WARNING: Code restructure failed: missing block: B:23:0x0050, code lost:
        android.util.Log.e("Socket", "Data size must fit into 2 bytes");
     */
    /* JADX WARNING: Code restructure failed: missing block: B:24:0x0057, code lost:
        return false;
     */
    /* JADX WARNING: Code restructure failed: missing block: B:25:0x0058, code lost:
        r0 = java.nio.ByteBuffer.allocate(2);
        r0.putShort((short) r6.length);
     */
    /* JADX WARNING: Code restructure failed: missing block: B:26:0x0061, code lost:
        r1 = r5._queuedData;
     */
    /* JADX WARNING: Code restructure failed: missing block: B:27:0x0063, code lost:
        monitor-enter(r1);
     */
    /* JADX WARNING: Code restructure failed: missing block: B:28:0x0064, code lost:
        if (r0 == null) goto L_0x007b;
     */
    /* JADX WARNING: Code restructure failed: missing block: B:31:0x006a, code lost:
        if (r5._socketType == com.miniclip.network.JavaSocket.SocketType.TCP) goto L_0x0072;
     */
    /* JADX WARNING: Code restructure failed: missing block: B:33:0x0070, code lost:
        if (r5._socketType != com.miniclip.network.JavaSocket.SocketType.TCPSSL) goto L_0x007b;
     */
    /* JADX WARNING: Code restructure failed: missing block: B:34:0x0072, code lost:
        r5._queuedData.add(r0.array());
     */
    /* JADX WARNING: Code restructure failed: missing block: B:35:0x007b, code lost:
        r5._queuedData.add(r6);
     */
    /* JADX WARNING: Code restructure failed: missing block: B:36:0x0080, code lost:
        monitor-exit(r1);
     */
    /* JADX WARNING: Code restructure failed: missing block: B:37:0x0081, code lost:
        r6 = r5._sendThread;
     */
    /* JADX WARNING: Code restructure failed: missing block: B:38:0x0083, code lost:
        monitor-enter(r6);
     */
    /* JADX WARNING: Code restructure failed: missing block: B:40:?, code lost:
        r5._sendThread.notify();
     */
    /* JADX WARNING: Code restructure failed: missing block: B:41:0x0089, code lost:
        monitor-exit(r6);
     */
    /* JADX WARNING: Code restructure failed: missing block: B:42:0x008a, code lost:
        return true;
     */
    public boolean sendData(byte[] bArr) {
        if (bArr.length == 0) {
            Log.e("Socket", "Data must not be empty");
            return false;
        }
        synchronized (this._state) {
            if (this._state == State.Closed) {
                Log.e("Socket", "Socket has been closed");
                return false;
            }
        }
    }

    public void setWorkBufferSize(int i) {
        this._workBufferSize = i;
    }
}
