# YT Streamer - Mobile & Client Integration API Documentation

YT Streamer API is designed to be fully compatible with mobile clients (Flutter, React Native, Native Android/iOS). It does not require any authentication headers or API keys.

## Base URL
*   Lokal: `http://localhost:3000`
*   PHP Hosting: `https://youtube.mertsamettaş.com.tr` (örnek subdomain)

---

## Endpoints

### 1. GET `/api/suggest` (Arama Önerileri / Autocomplete)
Kullanıcı arama çubuğuna yazarken anlık olarak YouTube otomatik tamamlama önerilerini getirir.

*   **Query Parameters:**
    *   `q` (string, required): Arama teriminin ilk harfleri (örn. `çağr`).
*   **Response:** `List<String>`
*   **Örnek Yanıt:**
    ```json
    [
      "çağrı sinci",
      "çağrı sinci insaf",
      "çağrı sinci bildiri",
      "çağrı merkezi"
    ]
    ```

---

### 2. GET `/api/search` (Video Arama)
`yt-dlp` kullanarak arama yapar ve ilk 20 video sonucunu döndürür.

*   **Query Parameters:**
    *   `q` (string, required): Arama kelimesi (Türkçe ve Unicode karakter desteği vardır).
*   **Response:** `List<Map>`
*   **Örnek Yanıt:**
    ```json
    [
      {
        "id": "Ikctv88FS7Q",
        "title": "Çağrı Sinci - İnsaf (Official Video)",
        "channel": "Çağrı Sinci",
        "duration": "3:45",
        "views": 2541085,
        "date": "20210515",
        "thumbnail": "https://i.ytimg.com/vi/Ikctv88FS7Q/hqdefault.jpg"
      }
    ]
    ```

---

### 3. GET `/api/video` (Detaylı Video Bilgileri)
Verilen video kimliğine (Video ID) ait tüm yt-dlp metadata verilerini JSON formatında döndürür.

*   **Query Parameters:**
    *   `id` (string, required): 11 karakterli YouTube video ID'si.
*   **Response:** `Map` (Video formatları, indirme linkleri, açıklamalar vb. detaylı teknik veriler)

---

### 4. GET `/api/stream` (Doğrudan Video Oynatma URL'si)
Mobil uygulamalardaki video oynatıcı (VideoPlayer, ExoPlayer vb.) widget'larında doğrudan kullanabileceğiniz geçici oynatma URL'sine yönlendirir (HTTP 302 Redirect).

*   **Query Parameters:**
    *   `id` (string, required): 11 karakterli YouTube video ID'si.
*   **Response:** `HTTP 302 Redirect` (YouTube CDN direct `.googlevideo` URL'sine yönlenir).
*   **Mobil Entegrasyon:**
    *   **Flutter (video_player):**
        ```dart
        VideoPlayerController.networkUrl(
          Uri.parse('https://youtube.mertsamettaş.com.tr/api/stream?id=Ikctv88FS7Q')
        );
        ```
    *   **React Native Video:**
        ```javascript
        <Video source={{ uri: 'https://youtube.mertsamettaş.com.tr/api/stream?id=Ikctv88FS7Q' }} />
        ```

---

### 5. GET `/api/download/mp4` (Yüksek Kalite Video İndirme)
Seçilen kalitede (360p, 480p, 720p, 1080p) ses ve görüntüsü sunucuda `ffmpeg` ile birleştirilmiş tam bir MP4 dosyası indirir.

*   **Query Parameters:**
    *   `id` (string, required): 11 karakterli YouTube video ID'si.
    *   `quality` (string, optional): `360`, `480`, `720`, `1080` (Varsayılan: `720`).
*   **Response:** `File Stream (video/mp4)`

---

### 6. GET `/api/download/mp3` (MP3 Müzik İndirme)
Videonun ses kanalını ayrıştırır ve seçilen kalitede (128, 192, 320 kbps) MP3 formatına dönüştürerek indirir.

*   **Query Parameters:**
    *   `id` (string, required): 11 karakterli YouTube video ID'si.
    *   `quality` (string, optional): `128`, `192`, `320` (Varsayılan: `192`).
*   **Response:** `File Stream (audio/mpeg)`

---

## Mobil Kod Örnekleri (Flutter / Dart)

### Dio ile Arama ve İndirme Sınıfı
```dart
import 'dart:io';
import 'package:dio/dio.dart';
import 'package:path_provider/path_provider.dart';

class YTStreamerApi {
  static const String baseUrl = "https://youtube.mertsamettaş.com.tr/api";
  final Dio _dio = Dio();

  // Arama Yapma
  Future<List<dynamic>> searchVideos(String query) async {
    final response = await _dio.get("$baseUrl/search", queryParameters: {"q": query});
    return response.data;
  }

  // Arama Önerileri Alma
  Future<List<String>> getSuggestions(String query) async {
    final response = await _dio.get("$baseUrl/suggest", queryParameters: {"q": query});
    return List<String>.from(response.data);
  }

  // MP4 Video İndirme (İlerleme çubuklu)
  Future<void> downloadVideo(String videoId, String quality, Function(int, int) onProgress) async {
    Directory appDocDir = await getApplicationDocumentsDirectory();
    String savePath = "${appDocDir.path}/$videoId.mp4";

    await _dio.download(
      "$baseUrl/download/mp4",
      savePath,
      queryParameters: {"id": videoId, "quality": quality},
      onReceiveProgress: onProgress,
    );
  }
}
```
