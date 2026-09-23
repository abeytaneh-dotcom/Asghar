plugins { id("com.android.application") }
android {
    namespace = "ir.khanehremap.smartobd"
    compileSdk = 35
    signingConfigs {
        create("stable") {
            storeFile = file("../signing/smartobd-release.jks")
            storePassword = "KhanehRemap2026!"
            keyAlias = "smartobd"
            keyPassword = "KhanehRemap2026!"
        }
    }
    defaultConfig {
        applicationId = "ir.khanehremap.smartobd"
        minSdk = 23
        targetSdk = 35
        versionCode = 7
        versionName = "2.4.1"
    }
    buildTypes {
        getByName("debug") { signingConfig = signingConfigs.getByName("stable") }
        getByName("release") {
            isMinifyEnabled = false
            signingConfig = signingConfigs.getByName("stable")
        }
    }
}
