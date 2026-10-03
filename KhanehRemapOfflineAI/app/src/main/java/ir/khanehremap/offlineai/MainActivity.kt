package ir.khanehremap.offlineai

import android.app.Activity
import android.app.AlertDialog
import android.content.ContentValues
import android.content.Context
import android.content.Intent
import android.database.sqlite.SQLiteDatabase
import android.database.sqlite.SQLiteOpenHelper
import android.graphics.Color
import android.graphics.Typeface
import android.graphics.drawable.GradientDrawable
import android.net.Uri
import android.os.Bundle
import android.provider.OpenableColumns
import android.text.InputType
import android.view.Gravity
import android.view.View
import android.view.ViewGroup
import android.widget.Button
import android.widget.EditText
import android.widget.HorizontalScrollView
import android.widget.ImageView
import android.widget.LinearLayout
import android.widget.ScrollView
import android.widget.TextView
import android.widget.Toast
import dev.ffmpegkit.llama.Llama
import dev.ffmpegkit.llama.LlamaConfig
import dev.ffmpegkit.llama.LlamaModel
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.io.File
import java.io.FileOutputStream
import java.util.Locale
import kotlin.math.min

class MainActivity : Activity() {

    companion object {
        private const val REQ_MODEL = 7001
        private const val MODEL_NAME = "khaneh_remap_model.gguf"
        private const val MAX_HISTORY_CHARS = 6500
    }

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.Main)
    private lateinit var db: ChatDb
    private lateinit var messagesBox: LinearLayout
    private lateinit var scroll: ScrollView
    private lateinit var input: EditText
    private lateinit var sendBtn: Button
    private lateinit var status: TextView
    private lateinit var modelBtn: Button

    private var model: LlamaModel? = null
    private var currentChatId: Long = -1L
    private var busy = false

    private val bg = Color.rgb(7, 19, 29)
    private val panel = Color.rgb(16, 38, 51)
    private val primary = Color.rgb(11, 111, 164)
    private val primaryDark = Color.rgb(11, 53, 88)
    private val text = Color.rgb(242, 247, 250)
    private val muted = Color.rgb(170, 187, 197)

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        window.statusBarColor = bg
        window.navigationBarColor = bg
        db = ChatDb(this)
        currentChatId = db.latestChatId().takeIf { it > 0 } ?: db.createChat("گفتگوی جدید")
        buildUi()
        renderConversation()
        val modelFile = modelFile()
        if (modelFile.exists() && modelFile.length() > 50_000_000L) {
            loadModel(modelFile)
        } else {
            status.text = "مدل انتخاب نشده — برای شروع روی «مدل» بزنید"
        }
    }

    private fun buildUi() {
        val root = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setBackgroundColor(bg)
            layoutDirection = View.LAYOUT_DIRECTION_RTL
            setPadding(dp(12), dp(10), dp(12), dp(8))
        }

        val header = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.CENTER_VERTICAL
            layoutDirection = View.LAYOUT_DIRECTION_RTL
            setPadding(dp(8), dp(8), dp(8), dp(8))
        }
        val logo = ImageView(this).apply {
            setImageResource(resources.getIdentifier("khaneh_remap_logo", "drawable", packageName))
            scaleType = ImageView.ScaleType.CENTER_CROP
        }
        header.addView(logo, LinearLayout.LayoutParams(dp(52), dp(52)).apply { marginStart = dp(10) })
        val titleCol = LinearLayout(this).apply { orientation = LinearLayout.VERTICAL }
        titleCol.addView(TextView(this).apply {
            text = "خانه ریمپ"
            setTextColor(text)
            textSize = 23f
            setTypeface(typeface, Typeface.BOLD)
            gravity = Gravity.RIGHT
        })
        titleCol.addView(TextView(this).apply {
            text = "هوش مصنوعی آفلاین"
            setTextColor(muted)
            textSize = 12f
            gravity = Gravity.RIGHT
        })
        header.addView(titleCol, LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f))
        root.addView(header)

        val actionsScroll = HorizontalScrollView(this).apply { isHorizontalScrollBarEnabled = false }
        val actions = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.RIGHT
            layoutDirection = View.LAYOUT_DIRECTION_RTL
        }
        modelBtn = smallButton("مدل") { pickModel() }
        val newBtn = smallButton("گفتگوی جدید") { newChat() }
        val historyBtn = smallButton("تاریخچه") { showHistory() }
        val memoryBtn = smallButton("حافظه") { showMemories() }
        actions.addView(modelBtn)
        actions.addView(newBtn)
        actions.addView(historyBtn)
        actions.addView(memoryBtn)
        actionsScroll.addView(actions)
        root.addView(actionsScroll, LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(48)))

        status = TextView(this).apply {
            setTextColor(muted)
            textSize = 12f
            gravity = Gravity.RIGHT
            setPadding(dp(8), dp(3), dp(8), dp(7))
        }
        root.addView(status)

        messagesBox = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            layoutDirection = View.LAYOUT_DIRECTION_RTL
            setPadding(dp(4), dp(8), dp(4), dp(8))
        }
        scroll = ScrollView(this).apply {
            isFillViewport = true
            addView(messagesBox, ViewGroup.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT))
        }
        root.addView(scroll, LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, 0, 1f))

        val composer = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.BOTTOM
            layoutDirection = View.LAYOUT_DIRECTION_RTL
            setPadding(dp(2), dp(8), dp(2), dp(2))
        }
        sendBtn = Button(this).apply {
            text = "ارسال"
            textSize = 14f
            setTextColor(Color.WHITE)
            background = rounded(primary, 18f)
            setOnClickListener { sendMessage() }
        }
        composer.addView(sendBtn, LinearLayout.LayoutParams(dp(78), dp(52)).apply { marginStart = dp(7) })
        input = EditText(this).apply {
            hint = "پیام خود را بنویسید…"
            setHintTextColor(Color.rgb(120, 143, 157))
            setTextColor(text)
            textSize = 16f
            gravity = Gravity.RIGHT or Gravity.CENTER_VERTICAL
            inputType = InputType.TYPE_CLASS_TEXT or InputType.TYPE_TEXT_FLAG_MULTI_LINE or InputType.TYPE_TEXT_FLAG_CAP_SENTENCES
            maxLines = 5
            minLines = 1
            background = rounded(panel, 20f)
            setPadding(dp(14), dp(8), dp(14), dp(8))
        }
        composer.addView(input, LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f))
        root.addView(composer)

        setContentView(root)
    }

    private fun sendMessage() {
        if (busy) return
        val userText = input.text.toString().trim()
        if (userText.isEmpty()) return
        val loaded = model
        if (loaded == null) {
            toast("ابتدا فایل مدل GGUF را انتخاب کنید")
            pickModel()
            return
        }
        input.setText("")
        db.addMessage(currentChatId, "user", userText)
        if (db.messageCount(currentChatId) == 1) db.renameChat(currentChatId, makeTitle(userText))
        maybeRemember(userText)
        addBubble("user", userText)
        val thinking = addBubble("assistant", "در حال فکر کردن…", temporary = true)
        setBusy(true)

        scope.launch {
            try {
                val prompt = withContext(Dispatchers.IO) { buildPrompt(currentChatId) }
                val system = buildSystemPrompt()
                val result = Llama.complete(
                    loaded,
                    prompt = prompt,
                    systemPrompt = system,
                    maxTokens = 420
                )
                val answer = result.text.trim().ifBlank { "پاسخی تولید نشد." }
                messagesBox.removeView(thinking)
                db.addMessage(currentChatId, "assistant", answer)
                addBubble("assistant", answer)
                status.text = "آماده • ${String.format(Locale.US, "%.1f", result.tokensPerSecond)} توکن/ثانیه"
            } catch (t: Throwable) {
                messagesBox.removeView(thinking)
                addBubble("assistant", "خطا در تولید پاسخ: ${t.message ?: t.javaClass.simpleName}")
                status.text = "خطا در پاسخ‌گویی"
            } finally {
                setBusy(false)
            }
        }
    }

    private fun buildPrompt(chatId: Long): String {
        val recent = db.recentMessages(chatId, 14)
        val sb = StringBuilder()
        sb.append("این مکالمه را ادامه بده. اگر اطلاعات کافی نیست، سؤال روشن‌کننده بپرس.\n\n")
        for (m in recent) {
            sb.append(if (m.role == "user") "کاربر: " else "دستیار: ")
                .append(m.content.trim()).append("\n")
        }
        sb.append("دستیار:")
        return sb.toString().takeLast(MAX_HISTORY_CHARS)
    }

    private fun buildSystemPrompt(): String {
        val memories = db.memories(20)
        val memoryText = if (memories.isEmpty()) "موردی ذخیره نشده است." else memories.joinToString("\n- ", prefix = "- ")
        return """
تو «دستیار هوشمند خانه ریمپ» هستی و کاملاً داخل همین گوشی اجرا می‌شوی.
زبان پیش‌فرض تو فارسی روان است. پاسخ‌ها دقیق، کاربردی و تا حد امکان کوتاه باشند.
اگر سؤال مبهم است یا برای جواب درست اطلاعات کم داری، خودت سؤال متقابل مناسب بپرس.
هیچ‌وقت ادعا نکن که به اینترنت، سرویس ابری یا اطلاعات زنده دسترسی داری.
اگر چیزی را مطمئن نیستی، شفاف بگو و حدس را به‌عنوان واقعیت بیان نکن.
در مسائل فنی، مراحل عملی و کم‌هزینه را در اولویت قرار بده.

حافظه‌های ذخیره‌شده کاربر:
$memoryText
        """.trimIndent()
    }

    private fun maybeRemember(userText: String) {
        val normalized = userText.lowercase(Locale.getDefault())
        val triggers = listOf("یادت بمونه", "یادت باشه", "به خاطر بسپار", "به خاطر داشته باش", "remember")
        if (triggers.any { normalized.contains(it) }) {
            db.addMemory(userText)
            toast("در حافظه محلی ذخیره شد")
        }
    }

    private fun pickModel() {
        val i = Intent(Intent.ACTION_OPEN_DOCUMENT).apply {
            addCategory(Intent.CATEGORY_OPENABLE)
            type = "*/*"
        }
        startActivityForResult(i, REQ_MODEL)
    }

    @Deprecated("Deprecated in Android API; kept for API 24 compatibility")
    override fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?) {
        super.onActivityResult(requestCode, resultCode, data)
        if (requestCode != REQ_MODEL || resultCode != RESULT_OK) return
        val uri = data?.data ?: return
        val name = displayName(uri) ?: "model.gguf"
        if (!name.lowercase(Locale.US).endsWith(".gguf")) {
            toast("فایل انتخابی باید با پسوند .gguf باشد")
            return
        }
        status.text = "در حال کپی مدل به حافظه برنامه…"
        setBusy(true)
        scope.launch {
            try {
                releaseModel()
                val target = modelFile()
                withContext(Dispatchers.IO) {
                    contentResolver.openInputStream(uri)?.use { source ->
                        FileOutputStream(target).use { output -> source.copyTo(output, 1024 * 1024) }
                    } ?: error("امکان خواندن فایل وجود ندارد")
                }
                loadModel(target)
            } catch (t: Throwable) {
                status.text = "خطا در وارد کردن مدل"
                toast(t.message ?: "خطای ناشناخته")
                setBusy(false)
            }
        }
    }

    private fun loadModel(file: File) {
        if (!file.exists()) return
        status.text = "در حال بارگذاری مدل ${humanSize(file.length())}…"
        setBusy(true)
        scope.launch {
            try {
                releaseModel()
                val loaded = Llama.loadModel(
                    modelPath = file.absolutePath,
                    config = LlamaConfig(
                        contextSize = 2048,
                        threads = 4,
                        gpuLayers = 0,
                        temperature = 0.65f,
                        topP = 0.9f,
                        topK = 40
                    )
                )
                model = loaded
                status.text = "آماده • مدل محلی ${humanSize(file.length())} • بدون اینترنت"
            } catch (t: Throwable) {
                model = null
                status.text = "بارگذاری مدل ناموفق بود"
                toast("مدل باز نشد: ${t.message ?: t.javaClass.simpleName}")
            } finally {
                setBusy(false)
            }
        }
    }

    private fun releaseModel() {
        model?.let {
            try { Llama.releaseModel(it) } catch (_: Throwable) { }
        }
        model = null
    }

    private fun newChat() {
        if (busy) return
        currentChatId = db.createChat("گفتگوی جدید")
        renderConversation()
    }

    private fun showHistory() {
        val chats = db.chats(50)
        if (chats.isEmpty()) return
        AlertDialog.Builder(this)
            .setTitle("تاریخچه گفتگوها")
            .setItems(chats.map { it.title }.toTypedArray()) { _, which ->
                currentChatId = chats[which].id
                renderConversation()
            }
            .setNegativeButton("بستن", null)
            .show()
    }

    private fun showMemories() {
        val mems = db.memories(100)
        if (mems.isEmpty()) {
            toast("هنوز حافظه‌ای ذخیره نشده")
            return
        }
        AlertDialog.Builder(this)
            .setTitle("حافظه محلی")
            .setItems(mems.toTypedArray()) { _, which ->
                AlertDialog.Builder(this)
                    .setMessage(mems[which])
                    .setPositiveButton("حذف") { _, _ -> db.deleteMemory(mems[which]); showMemories() }
                    .setNegativeButton("بستن", null)
                    .show()
            }
            .setNeutralButton("پاک کردن همه") { _, _ -> db.clearMemories(); toast("حافظه پاک شد") }
            .setNegativeButton("بستن", null)
            .show()
    }

    private fun renderConversation() {
        messagesBox.removeAllViews()
        val rows = db.messages(currentChatId)
        if (rows.isEmpty()) {
            val welcome = TextView(this).apply {
                text = "سلام، من دستیار آفلاین خانه ریمپ هستم.\nمدل کاملاً روی گوشی اجرا می‌شود. چه کاری انجام بدهم؟"
                setTextColor(muted)
                textSize = 16f
                gravity = Gravity.CENTER
                setPadding(dp(28), dp(50), dp(28), dp(30))
            }
            messagesBox.addView(welcome)
        } else {
            rows.forEach { addBubble(it.role, it.content) }
        }
        scroll.post { scroll.fullScroll(View.FOCUS_DOWN) }
    }

    private fun addBubble(role: String, content: String, temporary: Boolean = false): View {
        val row = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = if (role == "user") Gravity.LEFT else Gravity.RIGHT
            layoutDirection = View.LAYOUT_DIRECTION_LTR
            setPadding(dp(4), dp(4), dp(4), dp(4))
        }
        val bubble = TextView(this).apply {
            text = content
            setTextColor(if (temporary) muted else text)
            textSize = 16f
            gravity = Gravity.RIGHT
            layoutDirection = View.LAYOUT_DIRECTION_RTL
            setTextIsSelectable(!temporary)
            setPadding(dp(14), dp(10), dp(14), dp(10))
            background = rounded(if (role == "user") primaryDark else panel, 18f)
        }
        row.addView(bubble, LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 0.88f))
        row.addView(View(this), LinearLayout.LayoutParams(0, 1, 0.12f))
        messagesBox.addView(row, ViewGroup.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT))
        scroll.post { scroll.fullScroll(View.FOCUS_DOWN) }
        return row
    }

    private fun setBusy(value: Boolean) {
        busy = value
        sendBtn.isEnabled = !value
        modelBtn.isEnabled = !value
        input.isEnabled = !value
        sendBtn.alpha = if (value) 0.45f else 1f
    }

    private fun modelFile(): File {
        val dir = File(getExternalFilesDir(null) ?: filesDir, "models")
        if (!dir.exists()) dir.mkdirs()
        return File(dir, MODEL_NAME)
    }

    private fun displayName(uri: Uri): String? {
        contentResolver.query(uri, arrayOf(OpenableColumns.DISPLAY_NAME), null, null, null)?.use { c ->
            if (c.moveToFirst()) return c.getString(0)
        }
        return uri.lastPathSegment
    }

    private fun makeTitle(s: String): String {
        val clean = s.replace("\n", " ").trim()
        return clean.take(min(38, clean.length)).ifBlank { "گفتگوی جدید" }
    }

    private fun humanSize(bytes: Long): String {
        val gb = bytes / 1024.0 / 1024.0 / 1024.0
        return if (gb >= 1) String.format(Locale.US, "%.2f GB", gb) else String.format(Locale.US, "%.0f MB", bytes / 1024.0 / 1024.0)
    }

    private fun smallButton(label: String, click: () -> Unit): Button = Button(this).apply {
        text = label
        textSize = 12f
        setTextColor(text)
        isAllCaps = false
        background = rounded(panel, 16f)
        setPadding(dp(12), 0, dp(12), 0)
        setOnClickListener { click() }
        layoutParams = LinearLayout.LayoutParams(ViewGroup.LayoutParams.WRAP_CONTENT, dp(42)).apply { marginEnd = dp(6) }
    }

    private fun rounded(color: Int, radiusDp: Float): GradientDrawable = GradientDrawable().apply {
        setColor(color)
        cornerRadius = dp(radiusDp.toInt()).toFloat()
    }

    private fun dp(v: Int): Int = (v * resources.displayMetrics.density).toInt()
    private fun toast(s: String) = Toast.makeText(this, s, Toast.LENGTH_LONG).show()

    override fun onDestroy() {
        releaseModel()
        scope.cancel()
        db.close()
        super.onDestroy()
    }

    data class Msg(val role: String, val content: String)
    data class Chat(val id: Long, val title: String)

    class ChatDb(ctx: Context) : SQLiteOpenHelper(ctx, "khaneh_remap_ai.db", null, 1) {
        override fun onCreate(db: SQLiteDatabase) {
            db.execSQL("CREATE TABLE chats(id INTEGER PRIMARY KEY AUTOINCREMENT,title TEXT NOT NULL,created INTEGER NOT NULL)")
            db.execSQL("CREATE TABLE messages(id INTEGER PRIMARY KEY AUTOINCREMENT,chat_id INTEGER NOT NULL,role TEXT NOT NULL,content TEXT NOT NULL,created INTEGER NOT NULL)")
            db.execSQL("CREATE INDEX idx_msg_chat ON messages(chat_id,id)")
            db.execSQL("CREATE TABLE memories(id INTEGER PRIMARY KEY AUTOINCREMENT,content TEXT UNIQUE NOT NULL,created INTEGER NOT NULL)")
        }
        override fun onUpgrade(db: SQLiteDatabase, oldVersion: Int, newVersion: Int) = Unit

        fun createChat(title: String): Long = writableDatabase.insert("chats", null, ContentValues().apply {
            put("title", title); put("created", System.currentTimeMillis())
        })
        fun latestChatId(): Long = readableDatabase.rawQuery("SELECT id FROM chats ORDER BY id DESC LIMIT 1", null).use { c -> if (c.moveToFirst()) c.getLong(0) else -1L }
        fun renameChat(id: Long, title: String) { writableDatabase.update("chats", ContentValues().apply { put("title", title) }, "id=?", arrayOf(id.toString())) }
        fun addMessage(chatId: Long, role: String, content: String) { writableDatabase.insert("messages", null, ContentValues().apply { put("chat_id", chatId); put("role", role); put("content", content); put("created", System.currentTimeMillis()) }) }
        fun messageCount(chatId: Long): Int = readableDatabase.rawQuery("SELECT COUNT(*) FROM messages WHERE chat_id=?", arrayOf(chatId.toString())).use { c -> c.moveToFirst(); c.getInt(0) }
        fun messages(chatId: Long): List<Msg> = readableDatabase.rawQuery("SELECT role,content FROM messages WHERE chat_id=? ORDER BY id ASC", arrayOf(chatId.toString())).use { c -> buildList { while (c.moveToNext()) add(Msg(c.getString(0), c.getString(1))) } }
        fun recentMessages(chatId: Long, limit: Int): List<Msg> = readableDatabase.rawQuery("SELECT role,content FROM (SELECT id,role,content FROM messages WHERE chat_id=? ORDER BY id DESC LIMIT ?) ORDER BY id ASC", arrayOf(chatId.toString(), limit.toString())).use { c -> buildList { while (c.moveToNext()) add(Msg(c.getString(0), c.getString(1))) } }
        fun chats(limit: Int): List<Chat> = readableDatabase.rawQuery("SELECT id,title FROM chats ORDER BY id DESC LIMIT ?", arrayOf(limit.toString())).use { c -> buildList { while (c.moveToNext()) add(Chat(c.getLong(0), c.getString(1))) } }
        fun addMemory(content: String) { writableDatabase.insertWithOnConflict("memories", null, ContentValues().apply { put("content", content); put("created", System.currentTimeMillis()) }, SQLiteDatabase.CONFLICT_IGNORE) }
        fun memories(limit: Int): List<String> = readableDatabase.rawQuery("SELECT content FROM memories ORDER BY id DESC LIMIT ?", arrayOf(limit.toString())).use { c -> buildList { while (c.moveToNext()) add(c.getString(0)) } }
        fun deleteMemory(content: String) { writableDatabase.delete("memories", "content=?", arrayOf(content)) }
        fun clearMemories() { writableDatabase.delete("memories", null, null) }
    }
}
