package com.miniclip.crypto;

import com.mbridge.msdk.foundation.tools.SameMD5;
import com.unity3d.ads.core.data.datasource.AndroidStaticDeviceInfoDataSource;
import java.io.PrintStream;
import java.security.MessageDigest;
import javax.crypto.Cipher;
import javax.crypto.Mac;
import javax.crypto.SecretKeyFactory;
import javax.crypto.spec.IvParameterSpec;
import javax.crypto.spec.PBEKeySpec;
import javax.crypto.spec.SecretKeySpec;

public class crypto {
    public static byte[] decryptAES128(byte[] bArr, byte[] bArr2, byte[] bArr3) {
        return encrypt(bArr, bArr2, 2, "AES/CBC/PKCS5Padding", bArr3);
    }

    public static byte[] deriveKey(String str, int i, byte[] bArr, int i2) {
        byte[] bArr2;
        try {
            bArr2 = SecretKeyFactory.getInstance("PBKDF2withHmacSHA1").generateSecret(new PBEKeySpec(str.toCharArray(), bArr, i2, i * 8)).getEncoded();
        } catch (Exception e) {
            PrintStream printStream = System.err;
            printStream.println("Crypto: " + e.getMessage());
            bArr2 = null;
        }
        return bArr2 != null ? bArr2 : new byte[0];
    }

    private static byte[] encrypt(byte[] bArr, byte[] bArr2, int i, String str, byte[] bArr3) {
        byte[] bArr4;
        try {
            Cipher instance = Cipher.getInstance(str);
            instance.init(i, new SecretKeySpec(bArr2, str), new IvParameterSpec(bArr3));
            bArr4 = instance.doFinal(bArr);
        } catch (Exception e) {
            PrintStream printStream = System.err;
            printStream.println("Crypto: " + e.getMessage());
            bArr4 = null;
        }
        return bArr4 != null ? bArr4 : new byte[0];
    }

    public static byte[] encryptAES128(byte[] bArr, byte[] bArr2, byte[] bArr3) {
        return encrypt(bArr, bArr2, 1, "AES/CBC/PKCS5Padding", bArr3);
    }

    private static byte[] hash(byte[] bArr, String str) {
        byte[] bArr2;
        try {
            MessageDigest instance = MessageDigest.getInstance(str);
            instance.reset();
            instance.update(bArr);
            bArr2 = instance.digest();
        } catch (Exception e) {
            PrintStream printStream = System.err;
            printStream.println("Hash: " + e.getMessage());
            bArr2 = null;
        }
        return bArr2 != null ? bArr2 : new byte[0];
    }

    public static byte[] hashMD5(byte[] bArr) {
        return hash(bArr, SameMD5.TAG);
    }

    public static byte[] hashSHA1(byte[] bArr) {
        return hash(bArr, AndroidStaticDeviceInfoDataSource.ALGORITHM_SHA1);
    }

    public static byte[] hashSHA256(byte[] bArr) {
        return hash(bArr, "SHA-256");
    }

    public static byte[] hashSHA512(byte[] bArr) {
        return hash(bArr, "SHA-512");
    }

    private static byte[] hmac(byte[] bArr, byte[] bArr2, String str) {
        byte[] bArr3;
        try {
            Mac instance = Mac.getInstance(str);
            instance.init(new SecretKeySpec(bArr2, str));
            bArr3 = instance.doFinal(bArr);
        } catch (Exception e) {
            PrintStream printStream = System.err;
            printStream.println("Hash: " + e.getMessage());
            bArr3 = null;
        }
        return bArr3 != null ? bArr3 : new byte[0];
    }

    public static byte[] hmacMD5(byte[] bArr, byte[] bArr2) {
        return hmac(bArr, bArr2, "HmacMD5");
    }

    public static byte[] hmacSHA1(byte[] bArr, byte[] bArr2) {
        return hmac(bArr, bArr2, "HmacSHA1");
    }

    public static byte[] hmacSHA256(byte[] bArr, byte[] bArr2) {
        return hmac(bArr, bArr2, "HmacSHA256");
    }

    public static byte[] hmacSHA512(byte[] bArr, byte[] bArr2) {
        return hmac(bArr, bArr2, "HmacSHA512");
    }
}
