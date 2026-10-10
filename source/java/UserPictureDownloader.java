package com.miniclip.eightballpool.notification.userpicture;

import com.miniclip.network.HttpConnection;
import com.miniclip.network.HttpConnectionListener;
import io.bidmachine.iab.vast.tags.VastAttributes;
import java.util.ArrayList;
import java.util.List;

public class UserPictureDownloader implements HttpConnectionListener {
    private static final int DEFAULT_HTTP_REQUESTS_TIMEOUT_IN_MILLISECONDS = 5000;
    private static final int LARGE_PICTURE_SIZE_IN_PIXELS = 200;
    private static final int SMALL_PICTURE_SIZE_IN_PIXELS = 100;
    private String mAvatarsUrl = "";
    private final Object mLock = new Object();
    private List<UserPictureData> mUserPictureDatas;

    /* renamed from: com.miniclip.eightballpool.notification.userpicture.UserPictureDownloader$1  reason: invalid class name */
    static /* synthetic */ class AnonymousClass1 {
        static final /* synthetic */ int[] $SwitchMap$com$miniclip$eightballpool$notification$userpicture$UserPictureDownloader$PictureSize;

        /* JADX WARNING: Can't wrap try/catch for region: R(6:0|1|2|3|4|6) */
        /* JADX WARNING: Code restructure failed: missing block: B:7:?, code lost:
            return;
         */
        /* JADX WARNING: Failed to process nested try/catch */
        /* JADX WARNING: Missing exception handler attribute for start block: B:3:0x0012 */
        static {
            int[] iArr = new int[PictureSize.values().length];
            $SwitchMap$com$miniclip$eightballpool$notification$userpicture$UserPictureDownloader$PictureSize = iArr;
            iArr[PictureSize.SMALL.ordinal()] = 1;
            $SwitchMap$com$miniclip$eightballpool$notification$userpicture$UserPictureDownloader$PictureSize[PictureSize.LARGE.ordinal()] = 2;
        }
    }

    public enum PictureSize {
        SMALL,
        LARGE
    }

    private class UserPictureData {
        boolean connectionHasFinished;
        int connectionId;
        byte[] data;
        String url;
        String userId;

        private UserPictureData() {
            this.connectionId = -1;
            this.connectionHasFinished = false;
        }

        /* synthetic */ UserPictureData(UserPictureDownloader userPictureDownloader, AnonymousClass1 r2) {
            this();
        }
    }

    public UserPictureDownloader(String str) {
        this.mAvatarsUrl = str;
        this.mUserPictureDatas = new ArrayList();
    }

    private String getAvatarsUrl(String str, int i) {
        return this.mAvatarsUrl + "/" + str + "-" + i + VastAttributes.HORIZONTAL_POSITION + i + ".png?s=1&u=1";
    }

    private UserPictureData getUserPictureData(int i) {
        for (UserPictureData next : this.mUserPictureDatas) {
            if (next.connectionId == i) {
                return next;
            }
        }
        return null;
    }

    private boolean haveAllConnectionsEnded() {
        for (UserPictureData userPictureData : this.mUserPictureDatas) {
            if (!userPictureData.connectionHasFinished) {
                return false;
            }
        }
        return true;
    }

    public UserPictureDownloader addUser(String str, PictureSize pictureSize) {
        UserPictureData userPictureData = new UserPictureData(this, (AnonymousClass1) null);
        int i = AnonymousClass1.$SwitchMap$com$miniclip$eightballpool$notification$userpicture$UserPictureDownloader$PictureSize[pictureSize.ordinal()];
        int i2 = i != 1 ? i != 2 ? 0 : 200 : 100;
        userPictureData.userId = str;
        userPictureData.url = getAvatarsUrl(str, i2);
        this.mUserPictureDatas.add(userPictureData);
        return this;
    }

    public void download() throws InterruptedException {
        if (!this.mUserPictureDatas.isEmpty()) {
            for (UserPictureData next : this.mUserPictureDatas) {
                HttpConnection httpConnection = new HttpConnection(next.url, "GET", "", this, 5000);
                next.connectionId = httpConnection.getConnectionID().intValue();
                httpConnection.start();
            }
            synchronized (this.mLock) {
                this.mLock.wait();
            }
        }
    }

    public void downloadComplete(int i, int i2, byte[] bArr) {
        UserPictureData userPictureData = getUserPictureData(i);
        if (userPictureData != null) {
            userPictureData.connectionHasFinished = true;
            userPictureData.data = bArr;
        }
        if (haveAllConnectionsEnded()) {
            synchronized (this.mLock) {
                this.mLock.notifyAll();
            }
        }
    }

    public void downloadFailed(int i, String str) {
        UserPictureData userPictureData = getUserPictureData(i);
        if (userPictureData != null) {
            userPictureData.connectionHasFinished = true;
            userPictureData.data = null;
        }
        if (haveAllConnectionsEnded()) {
            synchronized (this.mLock) {
                this.mLock.notifyAll();
            }
        }
    }

    public byte[] getImageData(String str) {
        for (UserPictureData next : this.mUserPictureDatas) {
            if (next.userId.equals(str)) {
                return next.data;
            }
        }
        return null;
    }
}
