/**
 * Validates a YouTube search query to prevent command injection.
 * @param {string} query 
 * @returns {boolean}
 */
function isValidSearchQuery(query) {
    if (!query || typeof query !== 'string') return false;
    return /^[\p{L}\p{N}\s\-_.!?,#+&%@()]+$/u.test(query) && query.length <= 100;
}

/**
 * Validates a YouTube Video ID.
 * YouTube video IDs are exactly 11 characters long and contain alphanumeric characters, underscores, and hyphens.
 * @param {string} id 
 * @returns {boolean}
 */
function isValidVideoId(id) {
    if (!id || typeof id !== 'string') return false;
    return /^[a-zA-Z0-9_-]{11}$/.test(id);
}

module.exports = {
    isValidSearchQuery,
    isValidVideoId
};
