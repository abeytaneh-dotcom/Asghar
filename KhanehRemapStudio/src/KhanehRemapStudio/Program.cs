namespace KhanehRemapStudio;

internal static class Program
{
    private static readonly string LogDir =
        Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "KhanehRemapStudio");

    [STAThread]
    static void Main(string[] args)
    {
        Directory.CreateDirectory(LogDir);
        string log = Path.Combine(LogDir, "startup.log");

        try
        {
            File.WriteAllText(log, $"[{DateTime.Now:yyyy-MM-dd HH:mm:ss}] Starting Khaneh Remap Studio{Environment.NewLine}");
            ApplicationConfiguration.Initialize();

            Application.ThreadException += (_, e) => WriteCrash(log, "UI Thread", e.Exception);
            AppDomain.CurrentDomain.UnhandledException += (_, e) =>
            {
                if (e.ExceptionObject is Exception ex) WriteCrash(log, "Unhandled", ex);
            };

            File.AppendAllText(log, "Creating MainForm..." + Environment.NewLine);
            using var form = new MainForm();
            File.AppendAllText(log, "MainForm created successfully." + Environment.NewLine);

            if (args.Any(x => x.Equals("--self-test", StringComparison.OrdinalIgnoreCase)))
            {
                form.CreateControl();
                File.AppendAllText(log, "SELF-TEST OK" + Environment.NewLine);
                Environment.ExitCode = 0;
                return;
            }

            Application.Run(form);
        }
        catch (Exception ex)
        {
            WriteCrash(log, "Startup", ex);
            try
            {
                MessageBox.Show(
                    $"برنامه هنگام اجرا با خطا روبه‌رو شد.\n\n{ex.GetType().Name}: {ex.Message}\n\nگزارش خطا:\n{log}",
                    "Khaneh Remap Studio - Startup Error",
                    MessageBoxButtons.OK,
                    MessageBoxIcon.Error);
            }
            catch { }
            Environment.ExitCode = 100;
        }
    }

    private static void WriteCrash(string path, string stage, Exception ex)
    {
        try
        {
            File.AppendAllText(path,
                $"[{DateTime.Now:yyyy-MM-dd HH:mm:ss}] {stage} ERROR{Environment.NewLine}" +
                ex + Environment.NewLine + Environment.NewLine);
        }
        catch { }
    }
}