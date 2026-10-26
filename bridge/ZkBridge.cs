// Command-line bridge to the ZKTeco SDK (zkemkeeper COM).
// PHP's COM layer cannot marshal the SDK's by-reference integer out-parameters
// ("Type mismatch"), so Laravel shells out to this tool and reads JSON from stdout.
//
//   zkbridge.exe <info|users|logs|settime|clearlogs> <ip> <port> <machineNo>
using System;
using System.Collections.Generic;
using System.Web.Script.Serialization;

static class ZkBridge
{
    static int Main(string[] args)
    {
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
            try { data = Run(zk, cmd, mn); }
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

    static object Run(dynamic zk, string cmd, int mn)
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

            case "clearlogs":
            {
                zk.EnableDevice(mn, false);
                try { return (bool)zk.ClearGLog(mn); } finally { zk.EnableDevice(mn, true); }
            }

            case "users":
            {
                var list = new List<object>();
                zk.EnableDevice(mn, false);
                try
                {
                    if (zk.ReadAllUserID(mn))
                    {
                        string enroll = "", name = "", pwd = ""; int priv = 0; bool enabled = false;
                        while (zk.SSR_GetAllUserInfo(mn, out enroll, out name, out pwd, out priv, out enabled))
                            list.Add(new Dictionary<string, object> {
                                { "enroll_number", (enroll ?? "").Trim() }, { "name", (name ?? "").Trim() },
                                { "privilege", priv }, { "enabled", enabled } });
                    }
                }
                finally { zk.EnableDevice(mn, true); }
                return list;
            }

            case "logs":
            {
                var list = new List<object>();
                zk.EnableDevice(mn, false);
                try
                {
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
