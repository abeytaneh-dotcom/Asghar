package ir.hanaweb.carmap.ui

import android.content.Intent
import android.net.Uri
import android.provider.OpenableColumns
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import org.json.JSONArray
import org.json.JSONObject

private data class LocalMap(val uri: String, val title: String, val car: String, val ecu: String)

private class LocalMapStore(private val context: android.content.Context) {
    private val prefs = context.getSharedPreferences("carmap_local_library", android.content.Context.MODE_PRIVATE)
    fun read(): List<LocalMap> {
        return try {
            val arr = JSONArray(prefs.getString("maps", "[]") ?: "[]")
            (0 until arr.length()).map { i ->
                val j = arr.getJSONObject(i)
                LocalMap(j.getString("uri"), j.optString("title"), j.optString("car"), j.optString("ecu"))
            }
        } catch (_: Exception) { emptyList() }
    }
    fun write(maps: List<LocalMap>) {
        val array = JSONArray()
        maps.forEach { map ->
            array.put(JSONObject().put("uri", map.uri).put("title", map.title)
                .put("car", map.car).put("ecu", map.ecu))
        }
        prefs.edit().putString("maps", array.toString()).apply()
    }
}

@Composable
fun LocalLibraryScreen() {
    val context = LocalContext.current
    val store = remember { LocalMapStore(context.applicationContext) }
    var maps by remember { mutableStateOf(store.read()) }
    var car by remember { mutableStateOf("") }
    var ecu by remember { mutableStateOf("") }
    var query by remember { mutableStateOf("") }
    var message by remember { mutableStateOf("") }
    val picker = rememberLauncherForActivityResult(ActivityResultContracts.OpenDocument()) { uri: Uri? ->
        if (uri != null) {
            try {
                context.contentResolver.takePersistableUriPermission(uri, Intent.FLAG_GRANT_READ_URI_PERMISSION)
                var fileName = "نقشه"
                context.contentResolver.query(uri, null, null, null, null)?.use { cursor ->
                    val col = cursor.getColumnIndex(OpenableColumns.DISPLAY_NAME)
                    if (col >= 0 && cursor.moveToFirst()) fileName = cursor.getString(col) ?: fileName
                }
                maps = (maps + LocalMap(uri.toString(), fileName, car.trim(), ecu.trim())).distinctBy { it.uri }
                store.write(maps)
                message = "نقشه در کتابخانه ثبت شد"
            } catch (e: Exception) {
                message = "خطای خواندن فایل: " + (e.localizedMessage ?: "")
            }
        }
    }
    val results = maps.filter { (it.title + " " + it.car + " " + it.ecu).contains(query.trim(), ignoreCase = true) }
    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        item {
            Text("نقشه‌های من", style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
            Text("کتابخانه نقشه‌های شخصی شما؛ بدون اینترنت و ثبت‌نام", style = MaterialTheme.typography.bodyMedium)
        }
        item { OutlinedTextField(value = car, onValueChange = { car = it }, label = { Text("خودرو (اختیاری)") }, modifier = Modifier.fillMaxWidth(), singleLine = true) }
        item { OutlinedTextField(value = ecu, onValueChange = { ecu = it }, label = { Text("ایسیو یا نود (اختیاری)") }, modifier = Modifier.fillMaxWidth(), singleLine = true) }
        item {
            Button(onClick = { picker.launch(arrayOf("application/pdf", "image/*")) }, modifier = Modifier.fillMaxWidth()) {
                Text("افزودن فایل PDF یا تصویر")
            }
        }
        if (message.isNotBlank()) item { Text(message, style = MaterialTheme.typography.bodyMedium) }
        item { OutlinedTextField(value = query, onValueChange = { query = it }, label = { Text("جستجوی نقشه") }, modifier = Modifier.fillMaxWidth(), singleLine = true) }
        if (results.isEmpty()) item { Text(if (maps.isEmpty()) "هنوز نقشه‌ای اضافه نکرده‌اید." else "نتیجه‌ای یافت نشد.") }
        items(results, key = { it.uri }) { map ->
            Card(Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp)) {
                Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    Text(map.title, fontWeight = FontWeight.SemiBold)
                    val detail = listOf(map.car, map.ecu).filter { it.isNotBlank() }.joinToString(" • ")
                    if (detail.isNotBlank()) Text(detail, style = MaterialTheme.typography.bodySmall)
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        OutlinedButton(onClick = {
                            try {
                                val intent = Intent(Intent.ACTION_VIEW).apply {
                                    setDataAndType(Uri.parse(map.uri), if (map.title.endsWith(".pdf", ignoreCase = true)) "application/pdf" else "image/*")
                                    addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                                }
                                context.startActivity(intent)
                            } catch (_: Exception) {
                                message = "برای نمایش فایل، برنامه PDF یا گالری لازم است."
                            }
                        }) { Text("باز کردن") }
                        TextButton(onClick = {
                            maps = maps.filterNot { it.uri == map.uri }
                            store.write(maps)
                            message = "از فهرست حذف شد؛ فایل اصلی باقی مانده است."
                        }) { Text("حذف") }
                    }
                }
            }
        }
    }
}
