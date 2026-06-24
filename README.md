# YouTube MP3 & MP4 Downloader Platform

Bu proje, herhangi bir harici ücretli/ücretsiz YouTube API'si (Google Data API vb.) kullanmadan, arka planda tamamen **`yt-dlp`** ve **`ffmpeg`** kullanarak YouTube videolarını arama, izleme (HTML5 player ile) ve yüksek kalitede MP3/MP4 formatlarında indirme olanağı sunan modern, duyarlı ve performanslı bir Full Stack web uygulamasıdır.

Proje hem **Node.js (Express)** backend mimarisine hem de paylaşımlı Linux hostinglerde doğrudan çalıştırılabilmesi için özel olarak hazırlanmış **PHP** backend mimarisine sahiptir.

---

## 🌟 Özellikler
*   **Gerçek Zamanlı Arama & Öneriler (Autocomplete):** Kullanıcı arama çubuğuna yazdıkça, YouTube'un otomatik tamamlama öneri motorundan anlık sonuçlar listelenir.
*   **Arama Sonuçları:** İlk 20 sonuç; kapak resmi, süre, kanal adı, izlenme sayısı ve yayınlanma tarihi bilgileriyle birlikte listelenir.
*   **HTML5 Video Player:** İleri/geri sarma desteği (Range Request) sunan özel video oynatıcı.
*   **MP4 Video İndirme:** 360p, 480p, 720p, 1080p kalitelerinde ses ve görüntüyü sunucu tarafında `ffmpeg` ile birleştirerek gerçek MP4 indirir.
*   **MP3 Ses İndirme:** 128kbps, 192kbps ve 320kbps kalitelerinde ses dönüştürme seçeneği.
*   **API Playground & Entegrasyon Dokümantasyonu (`/docs.html`):** Mobil geliştiriciler (Flutter, React Native vb.) için canlı test alanı, JSON çıktıları ve entegrasyon kod örnekleri içeren görsel dökümantasyon sayfası.
*   **PWA Desteği:** Offline arayüz desteği ve telefona/bilgisayara uygulama olarak kurulabilme.
*   **Güvenlik:** Command Injection saldırılarına karşı sıkı Regex filtreleri ve Rate Limit koruması.
*   **Performans:** Arama sonuçları için 30 dakika, video detayları için 24 saat in-memory ve dosya tabanlı cache sistemi.

---

## 🛠️ Kurulum ve Çalıştırma

### Alternatif 1: Node.js ile Lokal/Sunucu Kurulumu (Zero-Config)

Projeyi bilgisayarınızda çalıştırmak oldukça basittir, `ffmpeg-static` kütüphanesi sayesinde bilgisayarınıza manuel olarak `ffmpeg` kurmanıza gerek kalmaz.

1.  Gerekli paketleri kurun:
    ```bash
    npm install
    ```
2.  Uygulamayı başlatın:
    ```bash
    npm start
    ```
3.  Tarayıcınızda açın:
    *   **Uygulama Arayüzü:** `http://localhost:3000`
    *   **API Test & Dokümantasyon Arayüzü:** `http://localhost:3000/docs.html`

> **Gereksinimler:** Sunucuda/Bilgisayarda `yt-dlp` yüklü olmalıdır. En güncel sürüm için `pip install -U yt-dlp` komutunu kullanabilirsiniz.

---

### Alternatif 2: PHP Hosting Kurulumu (Subdomain / Ana Dizin)

1.  **`youtube-platform-deploy.zip`** arşivini hosting dosya yöneticisinde ilgili klasöre yükleyin ve çıkarın.
2.  Klasörün içindeki `bin/yt-dlp` dosyasına yazma ve çalıştırma yetkisi verin (`chmod 755`).
3.  Sunucuda Python3 kurulu olduğundan emin olun (cPanel -> *Setup Python App* altından aktif edebilirsiniz).
4.  Eğer sunucunuzda global `ffmpeg` yüklü değilse, Linux uyumlu static `ffmpeg` binary dosyasını indirip `bin` klasörüne yükleyin ve `chmod 755` izni verin.
5.  Uygulamanıza ait API dökümantasyonuna `https://altalanadiniz.com/docs.html` adresinden ulaşabilirsiniz.

---

## 📂 Klasör Yapısı
*   `/public`: HTML5 Video Player, API Playground (`docs.html`), Tailwind CSS ve Vanilla JS içeren frontend dosyaları.
*   `/routes`: API rotaları (Node.js).
*   `/services`: `yt-dlp` çalıştırma ve önbellekleme servisleri.
*   `/utils`: Güvenlik doğrulama filtreleri.
*   `/php-backend`: PHP hosting için `api.php` ve `.htaccess` yönlendirme dosyası.

---

## 🔒 Lisans
Bu proje MIT lisansı altında lisanslanmıştır.
