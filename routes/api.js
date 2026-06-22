const express = require('express');
const router = express.Router();
const { isValidSearchQuery, isValidVideoId } = require('../utils/validator');
const { searchCache, videoCache } = require('../services/cacheService');
const ytService = require('../services/ytdlpService');
const { spawn } = require('child_process');

// In-memory mock DB for history and favorites (since no actual DB is requested, but features are)
const mockDb = {
    history: [],
    favorites: []
};

// GET /api/search?q=
router.get('/search', async (req, res) => {
    try {
        const query = req.query.q;
        if (!isValidSearchQuery(query)) {
            return res.status(400).json({ error: 'Invalid search query' });
        }

        const cached = searchCache.get(query);
        if (cached) {
            return res.json(cached);
        }

        const results = await ytService.search(query, 20);
        
        // Map required fields
        const mappedResults = results.map(item => ({
            id: item.id,
            title: item.title,
            channel: item.uploader || item.channel,
            duration: item.duration_string || item.duration,
            views: item.view_count,
            date: item.upload_date,
            thumbnail: item.thumbnails ? item.thumbnails[item.thumbnails.length - 1].url : null
        })).filter(item => item.id); // Ensure we only return valid videos

        searchCache.set(query, mappedResults);
        
        // Add to history
        if (!mockDb.history.find(h => h.query === query)) {
            mockDb.history.unshift({ query, timestamp: new Date() });
            if (mockDb.history.length > 50) mockDb.history.pop();
        }

        res.json(mappedResults);
    } catch (error) {
        console.error('Search error:', error);
        res.status(500).json({ error: 'Failed to perform search' });
    }
});

// GET /api/video?id=
router.get('/video', async (req, res) => {
    try {
        const id = req.query.id;
        if (!isValidVideoId(id)) {
            return res.status(400).json({ error: 'Invalid video ID' });
        }

        const cached = videoCache.get(id);
        if (cached) {
            return res.json(cached);
        }

        const info = await ytService.getVideoInfo(id);
        videoCache.set(id, info);
        res.json(info);
    } catch (error) {
        console.error('Video info error:', error);
        res.status(500).json({ error: 'Failed to fetch video info' });
    }
});

// GET /api/stream?id=
router.get('/stream', async (req, res) => {
    try {
        const id = req.query.id;
        if (!isValidVideoId(id)) {
            return res.status(400).json({ error: 'Invalid video ID' });
        }

        // We can use yt-dlp to stream to stdout and pipe to res, allowing express to handle range if we implement it,
        // or we get the direct URL and redirect or pipe. For true range request support natively with yt-dlp:
        const url = `https://www.youtube.com/watch?v=${id}`;
        
        // For range requests, the easiest robust way is to get the direct URL and fetch it with the range header.
        // Or redirect the client to the direct googlevideo URL (which supports range requests natively).
        const streamUrlArr = await ytService.getStreamUrl(id);
        const streamUrl = Array.isArray(streamUrlArr) ? streamUrlArr[0] : streamUrlArr;
        
        if (streamUrl && streamUrl.startsWith('http')) {
            // Redirecting directly allows the browser to handle range requests directly with YouTube's CDN
            return res.redirect(streamUrl);
        } else {
            return res.status(404).json({ error: 'Stream not found' });
        }
    } catch (error) {
        console.error('Stream error:', error);
        res.status(500).json({ error: 'Failed to start stream' });
    }
});

// GET /api/download/mp4?id=&quality=
router.get('/download/mp4', (req, res) => {
    const { id, quality } = req.query;
    if (!isValidVideoId(id)) {
        return res.status(400).json({ error: 'Invalid video ID' });
    }

    const height = quality || '1080';
    // Format string: bestvideo with height <= requested + bestaudio
    const format = `bestvideo[ext=mp4][height<=${height}]+bestaudio[ext=m4a]/best[ext=mp4]/best`;

    res.setHeader('Content-Disposition', `attachment; filename="${id}_${height}p.mp4"`);
    res.setHeader('Content-Type', 'video/mp4');

    const ytDlp = spawn('yt-dlp', [
        '-f', format,
        '-o', '-', // Output to stdout
        `https://www.youtube.com/watch?v=${id}`
    ]);

    ytDlp.stdout.pipe(res);

    ytDlp.stderr.on('data', (data) => {
        console.error(`yt-dlp download stderr: ${data}`);
    });

    ytDlp.on('close', (code) => {
        if (code !== 0) console.error(`yt-dlp process exited with code ${code}`);
    });
});

// GET /api/download/mp3?id=&quality=
router.get('/download/mp3', (req, res) => {
    const { id, quality } = req.query; // quality in kbps, e.g., 128, 192, 320
    if (!isValidVideoId(id)) {
        return res.status(400).json({ error: 'Invalid video ID' });
    }

    const kbps = ['128', '192', '320'].includes(quality) ? quality : '192';

    res.setHeader('Content-Disposition', `attachment; filename="${id}_${kbps}kbps.mp3"`);
    res.setHeader('Content-Type', 'audio/mpeg');

    const ytDlp = spawn('yt-dlp', [
        '-x', // extract audio
        '--audio-format', 'mp3',
        '--audio-quality', `${kbps}K`,
        '-o', '-', // Output to stdout
        `https://www.youtube.com/watch?v=${id}`
    ]);

    ytDlp.stdout.pipe(res);

    ytDlp.stderr.on('data', (data) => {
        console.error(`yt-dlp mp3 download stderr: ${data}`);
    });

    ytDlp.on('close', (code) => {
        if (code !== 0) console.error(`yt-dlp process exited with code ${code}`);
    });
});

// GET /api/history
router.get('/history', (req, res) => {
    res.json(mockDb.history);
});

// POST /api/favorites
router.post('/favorites', (req, res) => {
    const { video } = req.body;
    if (!video || !isValidVideoId(video.id)) {
        return res.status(400).json({ error: 'Invalid video object' });
    }
    
    if (!mockDb.favorites.find(f => f.id === video.id)) {
        mockDb.favorites.push(video);
    }
    res.json({ success: true, favorites: mockDb.favorites });
});

module.exports = router;
