const { spawn } = require('child_process');

/**
 * Executes a yt-dlp command safely.
 * @param {string[]} args Array of arguments for yt-dlp
 * @returns {Promise<any>} Parsed JSON or string output
 */
function execYtdlp(args) {
    return new Promise((resolve, reject) => {
        const process = spawn('yt-dlp', args);
        
        let stdoutData = '';
        let stderrData = '';

        process.stdout.on('data', (data) => {
            stdoutData += data.toString();
        });

        process.stderr.on('data', (data) => {
            stderrData += data.toString();
        });

        process.on('close', (code) => {
            if (code !== 0) {
                console.error(`yt-dlp error: ${stderrData}`);
                return reject(new Error(`yt-dlp exited with code ${code}: ${stderrData}`));
            }
            
            try {
                // If it's a JSON dump, try to parse it
                if (args.includes('--dump-json')) {
                    // yt-dlp might return multiple JSON objects separated by newlines for search results
                    const lines = stdoutData.trim().split('\n');
                    const jsonArr = lines.map(line => JSON.parse(line));
                    resolve(jsonArr.length === 1 ? jsonArr[0] : jsonArr);
                } else {
                    resolve(stdoutData.trim());
                }
            } catch (err) {
                // If parsing fails, just return the raw string
                resolve(stdoutData.trim());
            }
        });
        
        process.on('error', (err) => {
            console.error(`Failed to start yt-dlp: ${err.message}`);
            reject(err);
        });
    });
}

/**
 * Perform a search using ytsearch
 * @param {string} query 
 * @param {number} limit 
 */
async function search(query, limit = 20) {
    const args = [
        `ytsearch${limit}:${query}`,
        '--dump-json',
        '--no-playlist',
        '--flat-playlist'
    ];
    const results = await execYtdlp(args);
    // Ensure array
    return Array.isArray(results) ? results : [results];
}

/**
 * Get video metadata
 * @param {string} videoId 
 */
async function getVideoInfo(videoId) {
    const args = [
        `https://www.youtube.com/watch?v=${videoId}`,
        '--dump-json'
    ];
    return await execYtdlp(args);
}

/**
 * Get direct video stream URL
 * @param {string} videoId 
 */
async function getStreamUrl(videoId) {
    const args = [
        `https://www.youtube.com/watch?v=${videoId}`,
        '-g',
        '-f', 'best'
    ];
    return await execYtdlp(args);
}

module.exports = {
    search,
    getVideoInfo,
    getStreamUrl,
    execYtdlp
};
