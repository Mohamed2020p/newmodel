package com.miniclip.eightballpool.notification.support;

public class NotificationSupportData {
    public static final String DEFAULT_AVATARS_URL = "https://avatars-live.pool.miniclippt.com/avatars/pool/v2";
    private String mAvatarsUrlForLastSelectedEnvironment;

    public static class Builder {
        /* access modifiers changed from: private */
        public String mAvatarsUrlForLastSelectedEnvironment;

        public NotificationSupportData build() {
            return new NotificationSupportData(this);
        }

        public Builder setAvatarsUrlForLastSelectedEnvironment(String str) {
            this.mAvatarsUrlForLastSelectedEnvironment = str;
            return this;
        }
    }

    private NotificationSupportData(Builder builder) {
        this.mAvatarsUrlForLastSelectedEnvironment = builder.mAvatarsUrlForLastSelectedEnvironment;
    }

    public String getAvatarsUrlForLastSelectedEnvironment() {
        return this.mAvatarsUrlForLastSelectedEnvironment;
    }
}
