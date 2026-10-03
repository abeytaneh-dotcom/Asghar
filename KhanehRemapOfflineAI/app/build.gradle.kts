import java.util.Base64

plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
}

android {
    namespace = "ir.khanehremap.offlineai"
    compileSdk = 35

    defaultConfig {
        applicationId = "ir.khanehremap.offlineai"
        minSdk = 24
        targetSdk = 35
        versionCode = 1
        versionName = "1.0.0"
        ndk { abiFilters += listOf("arm64-v8a") }
    }

    buildTypes {
        release { isMinifyEnabled = false }
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
    kotlinOptions { jvmTarget = "17" }
}

val prepareBrandAssets by tasks.registering {
    val encoded = rootProject.file("brand/khaneh-remap-icon.b64")
    val output = project.file("src/main/res/drawable-nodpi/khaneh_remap_logo.png")
    inputs.file(encoded)
    outputs.file(output)
    doLast {
        output.parentFile.mkdirs()
        output.writeBytes(Base64.getDecoder().decode(encoded.readText().trim()))
    }
}

tasks.matching { it.name == "preBuild" }.configureEach {
    dependsOn(prepareBrandAssets)
}

dependencies {
    implementation("dev.ffmpegkit-maintained:llama-android:0.1.1")
    implementation("org.jetbrains.kotlinx:kotlinx-coroutines-android:1.9.0")
}
