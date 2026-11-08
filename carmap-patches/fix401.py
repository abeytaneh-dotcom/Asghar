from pathlib import Path
root = Path("app/src/main/java/ir/hanaweb/carmap")
screens = root / "ui/Screens.kt"
s = screens.read_text(encoding="utf-8")
a=s.index("@Composable\nfun HomeScreen(")
b=s.index("\n@Composable\nfun CarScreen(",a)
home = '''@Composable
fun HomeScreen(
    onCompany: (String) -> Unit,
    onAdmin: () -> Unit,
    onLearning: () -> Unit,
    onInfo: () -> Unit
) {
    var retry by remember { mutableIntStateOf(0) }
    var catalog by remember { mutableStateOf<DataState<ir.hanaweb.carmap.data.HomeDataResponse>>(DataState.Loading) }
    LaunchedEffect(retry) {
        catalog = DataState.Loading
        catalog = try {
            DataState.Ready(RemoteApi.service.getHomeData())
        } catch (error: Exception) {
            DataState.Error(error.localizedMessage ?: "خطای دریافت فهرست")
        }
    }
    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        item { ScreenHeader("کارمپ", "ورود به محیط مدیر محلی بدون ثبت‌نام و شماره موبایل") }
        item { LabelCard("پنل مدیر محلی • فعال", "برای ورود به پنل مدیر لمس کنید") { onAdmin() } }
        item { LabelCard("آموزش", "مطالب آموزشی موجود در برنامه") { onLearning() } }
        item { LabelCard("راهنمای برنامه", "درباره نسخه نصب‌شده") { onInfo() } }
        item { Text("فهرست آنلاین خودروها", style = MaterialTheme.typography.titleLarge) }
        when (val result = catalog) {
            DataState.Loading -> item {
                LabelCard("در حال دریافت فهرست آنلاین…", "استفاده از پنل مدیر به اینترنت وابسته نیست.")
            }
            is DataState.Ready -> {
                val companies = result.data.companies.orEmpty()
                if (companies.isEmpty()) {
                    item { LabelCard("فهرست آنلاین خالی است", "سرور خودرویی برنگردانده است.") }
                } else {
                    items(companies) { company ->
                        LabelCard(company.company.ifBlank { "شرکت بدون عنوان" }, "مشاهده خودروها") {
                            onCompany(company.company)
                        }
                    }
                }
            }
            is DataState.Error -> {
                item {
                    val unauthorized = result.message.contains("401") ||
                        result.message.contains("Unauthorized", ignoreCase = true)
                    LabelCard(
                        if (unauthorized) "دسترسی آنلاین توسط سرور تأیید نشد (401)" else "ارتباط با سرویس آنلاین برقرار نشد",
                        if (unauthorized)
                            "بخش‌های محلی برنامه فعال‌اند. دریافت اطلاعات سایت نیازمند مجوز معتبرِ صادرشده از سرور است."
                        else "بخش‌های محلی برنامه فعال‌اند؛ برای دریافت فهرست آنلاین دوباره تلاش کنید."
                    )
                }
                item { OutlinedButton(onClick = { retry++ }) { Text("تلاش دوباره برای فهرست آنلاین") } }
            }
        }
    }
}
'''
s=s[:a]+home+s[b:]
s=s.replace('Text("اتصال به سرور برقرار نشد.", style = MaterialTheme.typography.titleMedium)',
            'Text("بخش آنلاین در دسترس نیست", style = MaterialTheme.typography.titleMedium)')
screens.write_text(s,encoding="utf-8")
p=root/"MainActivity.kt"
s=p.read_text(encoding="utf-8")
old='HomeScreen(onCompany = { company -> nav.navigate(Routes.car(company)) })'
new='''HomeScreen(
                                    onCompany = { company -> nav.navigate(Routes.car(company)) },
                                    onAdmin = { nav.navigate(Routes.PROFILE) },
                                    onLearning = { nav.navigate(Routes.LEARNING) },
                                    onInfo = { nav.navigate(Routes.INFO) }
                                )'''
assert old in s
p.write_text(s.replace(old,new),encoding="utf-8")
p=Path("app/build.gradle.kts")
s=p.read_text(encoding="utf-8")
assert "versionCode = 1" in s
p.write_text(s.replace("versionCode = 1","versionCode = 2").replace('versionName = "1.0-local-admin"','versionName = "1.1-local-admin-offline"'),encoding="utf-8")
print("401 UI fallback patch applied")
