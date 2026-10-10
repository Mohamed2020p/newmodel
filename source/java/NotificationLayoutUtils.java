package com.miniclip.eightballpool.notification.layout;

import android.content.Context;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.graphics.Canvas;
import android.graphics.Paint;
import android.graphics.PorterDuff;
import android.graphics.PorterDuffXfermode;
import android.graphics.Rect;
import android.graphics.RectF;
import android.graphics.Typeface;
import android.util.TypedValue;
import android.widget.RemoteViews;
import com.miniclip.eightballpool.R;
import com.miniclip.eightballpool.notification.info.UserInfo;
import com.miniclip.eightballpool.notification.intent.NotificationIntentUtils;
import com.miniclip.eightballpool.notification.payload.NotificationPayloadParser;
import com.miniclip.eightballpool.notification.payload.NotificationPayloadParserException;
import com.miniclip.eightballpool.notification.userpicture.UserPictureDownloader;
import com.miniclip.notifications.MiniclipNotification;

public class NotificationLayoutUtils {
    private static final int DEFAULT_PAINT_COLOR = -16777216;
    private static final float DEFAULT_STROKE_WIDTH = 0.0f;
    private static final int LEVEL_TEXT_PAINT_FILL_COLOR = -1;
    private static final int LEVEL_TEXT_PAINT_STROKE_COLOR = -16777216;
    private static final float LEVEL_TEXT_PAINT_STROKE_WIDTH = 1.5f;
    private static final float LEVEL_TEXT_SIZE = 14.0f;
    private static final int NAME_TEXT_PAINT_FILL_COLOR = -1;
    private static final int NAME_TEXT_PAINT_STROKE_COLOR = -16773971;
    private static final float NAME_TEXT_PAINT_STROKE_WIDTH = 1.5f;
    private static final float NAME_TEXT_SIZE = 21.0f;
    private static final int PLAY_ACTION_BUTTON_LABEL_PAINT_FILL_COLOR = -1;
    private static final int PLAY_ACTION_BUTTON_LABEL_PAINT_STROKE_COLOR = -15367423;
    private static final float PLAY_ACTION_BUTTON_LABEL_PAINT_STROKE_WIDTH = 1.5f;
    private static final float PLAY_ACTION_BUTTON_LABEL_TEXT_SIZE = 20.0f;
    public static final int PLAY_INTENT_REQUEST_CODE = 1000;

    public static void addPlayAction(Context context, RemoteViews remoteViews, NotificationPayloadParser notificationPayloadParser) {
        String string;
        remoteViews.setOnClickPendingIntent(R.id.play_action_button, NotificationIntentUtils.createPlayIntent(context, notificationPayloadParser, 1000));
        try {
            string = notificationPayloadParser.getPlayActionLocalizedLabelText();
        } catch (NotificationPayloadParserException unused) {
            string = context.getString(R.string.notification_play_action_button_label);
        }
        remoteViews.setImageViewBitmap(R.id.play_action_label_image_view, getTextBitmap(context, string, -1, PLAY_ACTION_BUTTON_LABEL_PAINT_STROKE_COLOR, 20.0f, 1.5f));
    }

    private static int convertDipToPx(Context context, float f) {
        return (int) TypedValue.applyDimension(1, f, context.getResources().getDisplayMetrics());
    }

    private static void drawCenteredText(Canvas canvas, Paint paint, String str) {
        Rect rect = new Rect();
        canvas.getClipBounds(rect);
        int height = rect.height();
        int width = rect.width();
        paint.getTextBounds(str, 0, str.length(), rect);
        canvas.drawText(str, ((((float) width) / 2.0f) - (((float) rect.width()) / 2.0f)) - ((float) rect.left), ((((float) height) / 2.0f) + (((float) rect.height()) / 2.0f)) - ((float) rect.bottom), paint);
    }

    public static Bitmap getBitmapWithRoundedCorners(Bitmap bitmap) {
        return getBitmapWithRoundedCorners(bitmap, 25);
    }

    public static Bitmap getBitmapWithRoundedCorners(Bitmap bitmap, int i) {
        Bitmap createBitmap = Bitmap.createBitmap(bitmap.getWidth(), bitmap.getHeight(), Bitmap.Config.ARGB_8888);
        Canvas canvas = new Canvas(createBitmap);
        Paint paint = new Paint(1);
        paint.setColor(-16777216);
        float f = (float) i;
        canvas.drawRoundRect(new RectF(0.0f, 0.0f, (float) canvas.getWidth(), (float) canvas.getHeight()), f, f, paint);
        paint.setXfermode(new PorterDuffXfermode(PorterDuff.Mode.SRC_IN));
        canvas.drawBitmap(bitmap, 0.0f, 0.0f, paint);
        return createBitmap;
    }

    private static Paint getColoredFillPaint(Typeface typeface, float f, int i) {
        Paint paint = new Paint();
        paint.setTextSize(f);
        paint.setTextAlign(Paint.Align.LEFT);
        paint.setAntiAlias(true);
        paint.setColor(i);
        paint.setTypeface(typeface);
        return paint;
    }

    private static Paint getColoredStrokePaint(Typeface typeface, float f, int i, float f2) {
        Paint paint = new Paint();
        paint.setTextSize(f);
        paint.setAntiAlias(true);
        paint.setTextAlign(Paint.Align.LEFT);
        paint.setStyle(Paint.Style.STROKE);
        paint.setStrokeWidth(f2);
        paint.setColor(i);
        paint.setTypeface(typeface);
        return paint;
    }

    private static Bitmap getLevelTextBitmap(Context context, int i) {
        return getTextBitmap(context, String.valueOf(i), -1, -16777216, 14.0f, 1.5f);
    }

    private static Bitmap getNameTextBitmap(Context context, String str) {
        return getTextBitmap(context, str, -1, NAME_TEXT_PAINT_STROKE_COLOR, NAME_TEXT_SIZE, 1.5f);
    }

    public static Bitmap getTextBitmap(Context context, String str, int i, float f) {
        return getTextBitmap(context, str, i, -16777216, f, 0.0f, false);
    }

    public static Bitmap getTextBitmap(Context context, String str, int i, int i2, float f, float f2) {
        return getTextBitmap(context, str, i, i2, f, f2, true);
    }

    private static Bitmap getTextBitmap(Context context, String str, int i, int i2, float f, float f2, boolean z) {
        Typeface createFromAsset = Typeface.createFromAsset(context.getAssets(), "unpack/AdoraFresh.ttf");
        float convertDipToPx = (float) convertDipToPx(context, f);
        Paint coloredFillPaint = getColoredFillPaint(createFromAsset, convertDipToPx, i);
        Paint coloredStrokePaint = getColoredStrokePaint(createFromAsset, convertDipToPx, i2, f2);
        Rect textBounds = getTextBounds(coloredFillPaint, coloredStrokePaint, str, z);
        Bitmap createBitmap = Bitmap.createBitmap(textBounds.width(), textBounds.height(), Bitmap.Config.ARGB_8888);
        Canvas canvas = new Canvas(createBitmap);
        drawCenteredText(canvas, coloredFillPaint, str);
        if (z) {
            drawCenteredText(canvas, coloredStrokePaint, str);
        }
        return createBitmap;
    }

    private static Rect getTextBounds(Paint paint, Paint paint2, String str, boolean z) {
        Rect rect = new Rect();
        float textSize = paint2.getTextSize();
        paint2.setTextSize(((paint2.getStrokeWidth() * 0.1f) + 1.0f) * textSize);
        if (z) {
            paint = paint2;
        }
        paint.getTextBounds(str, 0, str.length(), rect);
        paint2.setTextSize(textSize);
        return rect;
    }

    public static Bitmap getUserPicture(Context context, UserPictureDownloader userPictureDownloader, String str) {
        byte[] imageData = userPictureDownloader.getImageData(str);
        return imageData == null ? BitmapFactory.decodeResource(context.getResources(), R.drawable.avatar_guest) : BitmapFactory.decodeByteArray(imageData, 0, imageData.length);
    }

    public static void setExpandedViewUserInfo(Context context, RemoteViews remoteViews, UserInfo userInfo, Bitmap bitmap, int i, int i2, int i3, int i4) {
        remoteViews.setImageViewBitmap(i, getBitmapWithRoundedCorners(bitmap));
        remoteViews.setImageViewBitmap(i2, getNameTextBitmap(context, userInfo.name));
        remoteViews.setImageViewBitmap(i3, getLevelTextBitmap(context, userInfo.level));
        remoteViews.setImageViewResource(i4, userInfo.getLevelStarResourceId());
    }

    public static void setShortViewInfo(RemoteViews remoteViews, MiniclipNotification miniclipNotification) {
        remoteViews.setTextViewText(R.id.notification_title, miniclipNotification.contentTitle);
        remoteViews.setTextViewText(R.id.notification_subtitle, miniclipNotification.contentText);
    }
}
