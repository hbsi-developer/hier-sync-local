// Command-line bridge to the ZKTeco SDK (zkemkeeper COM).
// PHP's COM layer cannot marshal the SDK's by-reference integer out-parameters
// ("Type mismatch"), so Laravel shells out to this tool and reads JSON from stdout.
//
//   zkbridge.exe <info|users|logs|settime> <ip> <port> <machineNo>
//   zkbridge.exe setuser <ip> <port> <machineNo> <enrollNo> <cardNo> [nameBase64]
using System;
using System.Collections.Generic;
using System.Web.Script.Serialization;

static class ZkBridge
{
    static int Main(string[] args)
    {
        Console.OutputEncoding = new System.Text.UTF8Encoding(false);
        var json = new JavaScriptSerializer { MaxJsonLength = int.MaxValue };
        try
        {
            if (args.Length < 4) throw new Exception("usage: zkbridge <cmd> <ip> <port> <machineNo>");
            string cmd = args[0], ip = args[1];
            int port = int.Parse(args[2]), mn = int.Parse(args[3]);

            Type t = Type.GetTypeFromProgID("zkemkeeper.ZKEM");
            if (t == null) throw new Exception("zkemkeeper.ZKEM is not registered (run sdk\\register-sdk.bat as administrator).");
            dynamic zk = Activator.CreateInstance(t);

            if (!zk.Connect_Net(ip, port))
            {
                int err = 0; zk.GetLastError(ref err);
                throw new Exception("Cannot connect to " + ip + ":" + port + " (SDK error " + err + ").");
            }

            object data;
            try { data = Run(zk, cmd, mn, args); }
            finally { try { zk.Disconnect(); } catch { } }

            Console.Out.Write(json.Serialize(new Dictionary<string, object> { { "ok", true }, { "data", data } }));
            return 0;
        }
        catch (Exception e)
        {
            Console.Out.Write(json.Serialize(new Dictionary<string, object> { { "ok", false }, { "error", e.Message } }));
            return 1;
        }
    }

    // Older devices (e.g. ZKTeco F8, black&white models) don't speak the SSR_* protocol: SSR_GetAllUserInfo
    // returns garbage there. The manual says to use the plain GetAllUserInfo / GetGeneralLogData instead.
    // Detect by checking whether the first SSR record has a sane (digits-only) user ID.
    static bool? _legacy;
    static bool IsLegacy(dynamic zk, int mn)
    {
        if (_legacy.HasValue) return _legacy.Value;
        bool legacy = false;
        try
        {
            if (zk.ReadAllUserID(mn))
            {
                string enroll = "", name = "", pwd = ""; int priv = 0; bool en = false;
                if (zk.SSR_GetAllUserInfo(mn, out enroll, out name, out pwd, out priv, out en))
                {
                    enroll = (enroll ?? "").Trim();
                    legacy = enroll.Length == 0 || !System.Text.RegularExpressions.Regex.IsMatch(enroll, "^[0-9]+$");
                }
            }
        }
        catch { legacy = true; }
        _legacy = legacy;
        return legacy;
    }

    static void ReadUsersLegacy(dynamic zk, int mn, List<object> list)
    {
        if (!zk.ReadAllUserID(mn)) return;
        int enroll = 0, priv = 0; string name = "", pwd = ""; bool enabled = false;
        while (zk.GetAllUserInfo(mn, ref enroll, ref name, ref pwd, ref priv, ref enabled))
            list.Add(new Dictionary<string, object> {
                { "enroll_number", enroll.ToString() }, { "name", (name ?? "").Trim() },
                { "card_number", ReadCard(zk) },
                { "privilege", priv }, { "enabled", enabled } });
    }

    static void ReadLogsLegacy(dynamic zk, int mn, List<object> list)
    {
        if (!zk.ReadGeneralLogData(mn)) return;
        int tm = 0, enroll = 0, em = 0, ver = 0, io = 0, y = 0, mo = 0, d = 0, h = 0, mi = 0;
        while (zk.GetGeneralLogData(mn, ref tm, ref enroll, ref em, ref ver, ref io, ref y, ref mo, ref d, ref h, ref mi))
            list.Add(new Dictionary<string, object> {
                { "enroll_number", enroll.ToString() },
                { "punched_at", string.Format("{0:D4}-{1:D2}-{2:D2} {3:D2}:{4:D2}:00", y, mo, d, h, mi) },
                { "verify_mode", ver }, { "in_out_mode", io }, { "work_code", 0 } });
    }

    static string ReadCard(dynamic zk)
    {
        try { string c = ""; zk.GetStrCardNumber(out c); c = (c ?? "").Trim(); return c == "0" ? "" : c; }
        catch { return ""; }
    }

    // Re-reads the user from the device so the caller sees what was actually stored.
    static string ReadCard(dynamic zk, string enroll, int mn, bool legacy)
    {
        try
        {
            string n = "", p = ""; int pr = 0; bool e = false;
            bool ok = legacy ? (bool)zk.GetUserInfo(mn, int.Parse(enroll), ref n, ref p, ref pr, ref e)
                             : (bool)zk.SSR_GetUserInfo(mn, enroll, out n, out p, out pr, out e);
            if (ok) return ReadCard(zk);
        }
        catch { }
        return "";
    }

    // Re-reads name/card/enabled as the device stored them, so callers never trust what was merely sent.
    static Dictionary<string, object> ReadBack(dynamic zk, string enroll, int mn, bool legacy)
    {
        string n = "", p = ""; int pr = 0; bool e = false;
        bool ok = legacy ? (bool)zk.GetUserInfo(mn, int.Parse(enroll), ref n, ref p, ref pr, ref e)
                         : (bool)zk.SSR_GetUserInfo(mn, enroll, out n, out p, out pr, out e);
        if (!ok) throw new Exception("Saved, but could not read user " + enroll + " back from the device.");
        return new Dictionary<string, object> {
            { "enroll_number", enroll }, { "name", (n ?? "").Trim() }, { "card_number", ReadCard(zk) }, { "enabled", e } };
    }

    static object Run(dynamic zk, string cmd, int mn, string[] args)
    {
        switch (cmd)
        {
            case "info":
            {
                string sn = ""; zk.GetSerialNumber(mn, out sn);
                int y = 0, mo = 0, d = 0, h = 0, mi = 0, s = 0;
                string time = null;
                if (zk.GetDeviceTime(mn, ref y, ref mo, ref d, ref h, ref mi, ref s))
                    time = string.Format("{0:D4}-{1:D2}-{2:D2} {3:D2}:{4:D2}:{5:D2}", y, mo, d, h, mi, s);
                return new Dictionary<string, object> { { "serial", (sn ?? "").Trim() }, { "time", time } };
            }

            case "settime":
                return (bool)zk.SetDeviceTime(mn);

            case "users":
            {
                var list = new List<object>();
                zk.EnableDevice(mn, false);
                try
                {
                    if (IsLegacy(zk, mn)) { ReadUsersLegacy(zk, mn, list); return list; }
                    if (zk.ReadAllUserID(mn))
                    {
                        string enroll = "", name = "", pwd = ""; int priv = 0; bool enabled = false;
                        while (zk.SSR_GetAllUserInfo(mn, out enroll, out name, out pwd, out priv, out enabled))
                            list.Add(new Dictionary<string, object> {
                                { "enroll_number", (enroll ?? "").Trim() }, { "name", (name ?? "").Trim() },
                                { "card_number", ReadCard(zk) },
                                { "privilege", priv }, { "enabled", enabled } });
                    }
                }
                finally { zk.EnableDevice(mn, true); }
                return list;
            }

            case "setuser":
            {
                // Edits an existing user's card number (and optionally name). The card must be set
                // BEFORE SSR_SetUserInfo; the existing password/privilege are read back and re-sent
                // because SSR_SetUserInfo would otherwise blank the password (manual 6.7, 6.12).
                if (args.Length < 6) throw new Exception("usage: setuser <ip> <port> <machineNo> <enrollNo> <cardNo> [nameBase64]");
                string enrollNo = args[4];
                string card = args[5].Trim();
                if (card == "") card = "0";
                string newName = args.Length > 6 && args[6] != ""
                    ? System.Text.Encoding.UTF8.GetString(Convert.FromBase64String(args[6])) : null;
                // Optional 8th arg: "1" enables, "0" disables the user. Omitted/blank keeps the current state.
                bool? newEnabled = args.Length > 7 && (args[7] == "1" || args[7] == "0") ? (bool?)(args[7] == "1") : null;

                zk.EnableDevice(mn, false);
                try
                {
                    string name = "", pwd = ""; int priv = 0; bool en = false;
                    bool legacy = IsLegacy(zk, mn);
                    int enrollInt = 0;
                    if (legacy && !int.TryParse(enrollNo, out enrollInt)) throw new Exception("Enroll number must be numeric on this device.");
                    bool found = legacy
                        ? (bool)zk.GetUserInfo(mn, enrollInt, ref name, ref pwd, ref priv, ref en)
                        : (bool)zk.SSR_GetUserInfo(mn, enrollNo, out name, out pwd, out priv, out en);
                    if (!found) throw new Exception("User " + enrollNo + " was not found on the device.");

                    if (newEnabled.HasValue) en = newEnabled.Value;
                    zk.SetStrCardNumber(card);
                    bool saved = legacy
                        ? (bool)zk.SetUserInfo(mn, enrollInt, newName ?? name, pwd, priv, en)
                        : (bool)zk.SSR_SetUserInfo(mn, enrollNo, newName ?? name, pwd, priv, en);
                    if (!saved)
                    {
                        int err = 0; zk.GetLastError(ref err);
                        throw new Exception("Device refused the update (SDK error " + err + "). The card number may already belong to another user.");
                    }
                    zk.RefreshData(mn);
                    return ReadBack(zk, enrollNo, mn, legacy);
                }
                finally { zk.EnableDevice(mn, true); }
            }

            case "logs":
            {
                var list = new List<object>();
                zk.EnableDevice(mn, false);
                try
                {
                    if (IsLegacy(zk, mn)) { ReadLogsLegacy(zk, mn, list); return list; }
                    if (zk.ReadGeneralLogData(mn))
                    {
                        string enroll = ""; int ver = 0, io = 0, y = 0, mo = 0, d = 0, h = 0, mi = 0, s = 0, wc = 0;
                        while (zk.SSR_GetGeneralLogData(mn, out enroll, out ver, out io, out y, out mo, out d, out h, out mi, out s, ref wc))
                            list.Add(new Dictionary<string, object> {
                                { "enroll_number", (enroll ?? "").Trim() },
                                { "punched_at", string.Format("{0:D4}-{1:D2}-{2:D2} {3:D2}:{4:D2}:{5:D2}", y, mo, d, h, mi, s) },
                                { "verify_mode", ver }, { "in_out_mode", io }, { "work_code", wc } });
                    }
                }
                finally { zk.EnableDevice(mn, true); }
                return list;
            }

            default:
                throw new Exception("unknown command: " + cmd);
        }
    }
}
