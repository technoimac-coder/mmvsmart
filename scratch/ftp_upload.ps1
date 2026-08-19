$ftpHost = "147.50.230.21"
$ftpUser = "mmvsc"
$ftpPass = "Password@123"
$remoteDir = "MMVSmart.mmvschool.ac.th"

$files = @(
    ".htaccess",
    "index.php",
    "config.php",
    "api.php",
    "google-mock.js",
    "teacher.html",
    "student.html",
    "logo.png",
    "test_db.php",
    "clear_avatars.php",
    "migrate_student_avatar.php",
    "migrate_clubs_image.php",
    "migrate_clubs_location.php",
    "check_stu_dir.php",
    "check_login_users.php",
    "test_login_direct.php"
)

# Resolve scratch path dynamically to prevent encoding issues with Thai username in absolute path
$resolvedPath = Resolve-Path "$PSScriptRoot\..\.."
$localPath = $resolvedPath.Path
Write-Host "Resolved Local Scratch Path: $localPath"

foreach ($file in $files) {
    $localFile = Join-Path $localPath $file
    $remoteUrl = "ftp://$ftpHost/$remoteDir/$file"
    
    if (Test-Path $localFile) {
        Write-Host "Uploading $file to $remoteUrl..."
        $request = [System.Net.FtpWebRequest]::Create($remoteUrl)
        $request.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
        $request.Credentials = New-Object System.Net.NetworkCredential($ftpUser, $ftpPass)
        
        $fileBytes = [System.IO.File]::ReadAllBytes($localFile)
        $request.ContentLength = $fileBytes.Length
        
        $requestStream = $request.GetRequestStream()
        $requestStream.Write($fileBytes, 0, $fileBytes.Length)
        $requestStream.Close()
        
        $response = $request.GetResponse()
        Write-Host "Finished uploading $file. Server response status: $($response.StatusDescription)"
        $response.Close()
    } else {
        Write-Warning "Local file not found: $localFile"
    }
}

Write-Host "FTP Upload Complete!"
