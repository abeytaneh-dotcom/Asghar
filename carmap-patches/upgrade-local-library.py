from pathlib import Path

root = Path("app/src/main/java/ir/hanaweb/carmap")
screens = root / "ui/Screens.kt"
s = screens.read_text(encoding="utf-8")
start = s.index("@Composable\nfun HomeScreen(")
end = s.index("\n@Composable\nfun CarScreen(", start)
new_home = r'''@Composable
fun HomeScreen(
    onCompany: (String) -> Unit,
    onLibrary: () -> Unit,
    onProfile: () -> Unit
) {
    var request by remember { mutableIntStateOf(0) }
    var loading by remember { mutableStateOf(false) }
    var notice by remember { mutableStateOf("") }
    var companies by remember { mutableStateOf<List<ir.hanaweb.carmap.data.Company>>(emptyList()) }
    LaunchedEffect(request) {
        if (request == 0) return@LaunchedEffect
        loading = true
        notice = ""
        try {
            companies = RemoteApi.service.getHomeData().companies.orEmpty()
            if (companies.isEmpty()) notice = "فهرست آنلاین خالی است."
        } catch (e: Exception) {
            companies = emptyList()
            notice = if (e is retrofit2.HttpException && e.code() == 401)
                "سرور برای نمایش نقشه‌های آنلاین مجوز معتبر می‌خواهد. نقشه‌های شخصی و پنل مدیر محلی بدون ثبت‌نام در دسترس‌اند."
            else "اتصال به سرور برقرار نشد. نقشه‌های شخصی همچنان قابل استفاده‌اند."
        } finally {
            loading = false
        }
    }
    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        item { ScreenHeader("کارمپ", "مرکز نقشه‌ها و اطلاعات فنی خودرو") }
        item { LabelCard("نقشه‌های من", "افزودن و جستجوی نقشه‌های PDF یا تصویری خودتان؛ بدون اینترنت") { onLibrary() } }
        item { LabelCard("مدیر محلی", "ورود مستقیم به مدیریت و تنظیمات بدون شماره موبایل") { onProfile() } }
        item { Text("بخش آنلاین CarMap", style = MaterialTheme.typography.titleLarge) }
        item {
            OutlinedButton(onClick = { request += 1 }, enabled = !loading) {
                Text(if (loading) "در حال بررسی..." else "دریافت فهرست آنلاین")
            }
        }
        if (loading) item { CircularProgressIndicator() }
        if (notice.isNotBlank()) item { Text(notice, style = MaterialTheme.typography.bodyMedium) }
        items(companies) { company ->
            LabelCard(company.company.ifBlank { "شرکت" }, "مشاهده خودروها") { onCompany(company.company) }
        }
    }
}
'''
s = s[:start] + new_home + s[end:]
screens.write_text(s, encoding="utf-8")

main = root / "MainActivity.kt"
m = main.read_text(encoding="utf-8")
old = 'HomeScreen(onCompany = { company -> nav.navigate(Routes.car(company)) })'
assert old in m, "MainActivity navigation marker changed"
m = m.replace(old, '''HomeScreen(
                                    onCompany = { company -> nav.navigate(Routes.car(company)) },
                                    onLibrary = { nav.navigate(Routes.PACK) },
                                    onProfile = { nav.navigate(Routes.PROFILE) }
                                )''')
m = m.replace('composable(Routes.PACK) { PackScreen() }','composable(Routes.PACK) { LocalLibraryScreen() }')
main.write_text(m, encoding="utf-8")

navigation = root / "navigation/Navigation.kt"
n = navigation.read_text(encoding="utf-8")
n = n.replace('Triple(Routes.PACK, "بسته‌ها", "▣")', 'Triple(Routes.PACK, "نقشه‌های من", "▣")')
navigation.write_text(n,encoding="utf-8")

build = Path("app/build.gradle.kts")
b = build.read_text(encoding="utf-8")
b = b.replace('applicationId = "ir.hanaweb.carmap"', 'applicationId = "ir.hanaweb.carmap.local"')
b = b.replace("versionCode = 1", "versionCode = 3").replace("versionCode = 2", "versionCode = 3")
b = b.replace('versionName = "1.0-local-admin"', 'versionName = "1.2-local-library"').replace('versionName = "1.1-local-admin"', 'versionName = "1.2-local-library"')
build.write_text(b,encoding="utf-8")

resources = Path("app/src/main/res/values/strings.xml")
resources.write_text('<resources><string name="app_name">CarMap محلی</string></resources>\n',encoding="utf-8")
print("Local file library and offline-first home integrated")
