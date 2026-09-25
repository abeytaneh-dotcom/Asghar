plugins { id("com.android.application") }

android {
    namespace = "ir.khanehremap.smartobdpro"
    compileSdk = 35

    defaultConfig {
        applicationId = "ir.khanehremap.smartobdpro"
        minSdk = 23
        targetSdk = 35
        versionCode = 1
        versionName = "4.0.0"
    }

    buildTypes {
        getByName("release") {
            isMinifyEnabled = false
        }
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
}
