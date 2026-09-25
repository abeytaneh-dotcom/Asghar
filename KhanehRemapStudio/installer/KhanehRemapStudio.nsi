Unicode true
!define APPNAME "Khaneh Remap Studio Pro"
!define COMPANY "Khaneh Remap"
!define EXE "KhanehRemapStudio.exe"

OutFile "KhanehRemapStudio-Setup-x64.exe"
InstallDir "$LOCALAPPDATA\Programs\KhanehRemapStudio"
RequestExecutionLevel user
SetCompressor /SOLID lzma

Page directory
Page instfiles
UninstPage uninstConfirm
UninstPage instfiles

Section "Install"
  SetOutPath "$INSTDIR"
  File "..\src\KhanehRemapStudio\bin\Release\net8.0-windows\win-x64\publish\KhanehRemapStudio.exe"
  CreateDirectory "$SMPROGRAMS\Khaneh Remap Studio"
  CreateShortcut "$SMPROGRAMS\Khaneh Remap Studio\Khaneh Remap Studio.lnk" "$INSTDIR\${EXE}"
  CreateShortcut "$DESKTOP\Khaneh Remap Studio.lnk" "$INSTDIR\${EXE}"
  WriteUninstaller "$INSTDIR\Uninstall.exe"
  WriteRegStr HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\KhanehRemapStudio" "DisplayName" "${APPNAME}"
  WriteRegStr HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\KhanehRemapStudio" "Publisher" "${COMPANY}"
  WriteRegStr HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\KhanehRemapStudio" "DisplayVersion" "1.0.0"
  WriteRegStr HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\KhanehRemapStudio" "UninstallString" "$INSTDIR\Uninstall.exe"
SectionEnd

Section "Uninstall"
  Delete "$DESKTOP\Khaneh Remap Studio.lnk"
  Delete "$SMPROGRAMS\Khaneh Remap Studio\Khaneh Remap Studio.lnk"
  RMDir "$SMPROGRAMS\Khaneh Remap Studio"
  Delete "$INSTDIR\${EXE}"
  Delete "$INSTDIR\Uninstall.exe"
  RMDir "$INSTDIR"
  DeleteRegKey HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\KhanehRemapStudio"
SectionEnd