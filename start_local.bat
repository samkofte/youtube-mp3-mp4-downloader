@echo off
echo ===================================================
echo YT Streamer - PHP Local Server Başlatılıyor...
echo ===================================================
echo Lutfen bu siyah ekrani KAPATMAYIN!
echo Tarayiciniz otomatik olarak aciliyor...

:: Eski Node.js portunu kullaniyoruz ki tarayiciniz yadirgamasin
start http://localhost:3000

:: PHP sunucusunu router.php ile calistiriyoruz (eski onbellek yollarini yakalamak icin)
php -S localhost:3000 router.php
pause
