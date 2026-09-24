using System;
using System.IO;
using System.Windows.Forms;

namespace KhanehRemapEcuStation;

internal static class Program
{
    static readonly string CrashLogPath = Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
        "KhanehRemap",
        "ECUStation",
        "crash.log");

    [STAThread]
    static void Main()
    {
        try
        {
            Application.SetUnhandledExceptionMode(UnhandledExceptionMode.CatchException);
            Application.ThreadException += (_, e) => HandleCrash(e.Exception);
            AppDomain.CurrentDomain.UnhandledException += (_, e) =>
            {
                if (e.ExceptionObject is Exception ex) HandleCrash(ex);
            };

            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);
            Application.Run(new MainForm());
        }
        catch (Exception ex)
        {
            HandleCrash(ex);
        }
    }

    static void HandleCrash(Exception ex)
    {
        try
        {
            Directory.CreateDirectory(Path.GetDirectoryName(CrashLogPath)!);
            File.AppendAllText(
                CrashLogPath,
                $"[{DateTime.Now:yyyy-MM-dd HH:mm:ss}]\r\n{ex}\r\n------------------------------\r\n");
        }
        catch { }

        try
        {
            MessageBox.Show(
                "خطای داخلی نرم‌افزار ثبت شد و برنامه بسته نمی‌شود.\n\n" +
                "فایل گزارش خطا در این مسیر ذخیره شده است:\n" + CrashLogPath +
                "\n\nجزئیات: " + ex.Message,
                "خانه ریمپ - گزارش خطا",
                MessageBoxButtons.OK,
                MessageBoxIcon.Error);
        }
        catch { }
    }
}
