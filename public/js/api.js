/* api.js */

const API_BASE = '/api';

/**
 * Perform search with debounce handling in the UI layer
 */
async function searchVideos(query) {
    const response = await fetch(`${API_BASE}/search?q=${encodeURIComponent(query)}`);
    if (!response.ok) throw new Error('Search failed');
    return response.json();
}

/**
 * Get video details
 */
async function getVideoDetails(id) {
    const response = await fetch(`${API_BASE}/video?id=${id}`);
    if (!response.ok) throw new Error('Failed to fetch video details');
    return response.json();
}

/**
 * Get download URL for mp4
 */
function getMp4Url(id, quality) {
    return `${API_BASE}/download/mp4?id=${id}&quality=${quality}`;
}

/**
 * Get download URL for mp3
 */
function getMp3Url(id, quality) {
    return `${API_BASE}/download/mp3?id=${id}&quality=${quality}`;
}

/**
 * Get stream URL for HTML5 player
 */
function getStreamUrl(id) {
    return `${API_BASE}/stream?id=${id}`;
}

/**
 * Get autocomplete suggestions
 */
async function getSuggestions(query) {
    const response = await fetch(`${API_BASE}/suggest?q=${encodeURIComponent(query)}`);
    if (!response.ok) return [];
    return response.json();
}

window.api = {
    searchVideos,
    getVideoDetails,
    getMp4Url,
    getMp3Url,
    getStreamUrl,
    getSuggestions
};
