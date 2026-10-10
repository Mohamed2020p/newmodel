package com.miniclip.network;

import android.content.Context;
import java.util.HashMap;

public class HttpConnectionsManager {
    protected static HttpConnectionsManager instance = new HttpConnectionsManager();
    protected HashMap<Integer, HttpConnection> connections = new HashMap<>();
    public Context context;
    protected int lastAssignedID = 0;

    protected HttpConnectionsManager() {
    }

    private synchronized void reset() {
        this.connections = new HashMap<>();
        this.lastAssignedID = 0;
    }

    public static HttpConnectionsManager sharedInstance() {
        return instance;
    }

    public synchronized Integer addConnection(HttpConnection httpConnection) {
        HashMap<Integer, HttpConnection> hashMap = this.connections;
        int i = this.lastAssignedID + 1;
        this.lastAssignedID = i;
        hashMap.put(Integer.valueOf(i), httpConnection);
        return Integer.valueOf(this.lastAssignedID);
    }

    public synchronized HttpConnection getConnection(Integer num) {
        return this.connections.get(num);
    }

    public synchronized void removeConnection(Integer num) {
        this.connections.remove(num);
    }

    public synchronized String stateDescription() {
        return "HttpConnectionsManager: currently holding " + this.connections.size() + " connections.\n" + this.connections.toString();
    }
}
