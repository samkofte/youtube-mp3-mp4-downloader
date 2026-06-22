class CacheService {
    constructor(ttlSeconds = 60 * 60) { // Default 1 hour
        this.cache = new Map();
        this.ttl = ttlSeconds * 1000;
    }

    set(key, value) {
        const expiresAt = Date.now() + this.ttl;
        this.cache.set(key, { value, expiresAt });
    }

    get(key) {
        const item = this.cache.get(key);
        if (!item) return null;

        if (Date.now() > item.expiresAt) {
            this.cache.delete(key);
            return null;
        }

        return item.value;
    }

    delete(key) {
        this.cache.delete(key);
    }
    
    clear() {
        this.cache.clear();
    }
}

// Singleton instances for different data types
const searchCache = new CacheService(30 * 60); // 30 mins
const videoCache = new CacheService(60 * 60 * 24); // 24 hours

module.exports = {
    searchCache,
    videoCache
};
