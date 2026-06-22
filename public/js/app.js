/* app.js */

let currentVideo = null;
let searchTimeout = null;

const app = {
    init() {
        this.bindEvents();
        this.loadTrending();
        this.registerServiceWorker();
    },

    bindEvents() {
        const searchInput = document.getElementById('searchInput');
        const suggestionsDropdown = document.getElementById('suggestionsDropdown');

        searchInput.addEventListener('input', async (e) => {
            clearTimeout(searchTimeout);
            const query = e.target.value.trim();
            if (query.length > 0) {
                // Fetch suggestions instantly
                try {
                    const suggestions = await window.api.getSuggestions(query);
                    this.renderSuggestions(suggestions);
                } catch (err) {
                    console.error('Failed to get suggestions', err);
                }
                
                // Perform search with debounce
                searchTimeout = setTimeout(() => {
                    this.performSearch(query);
                    suggestionsDropdown.classList.add('hidden');
                }, 800);
            } else {
                suggestionsDropdown.classList.add('hidden');
                this.loadTrending();
            }
        });

        // Hide suggestions when clicking outside
        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !suggestionsDropdown.contains(e.target)) {
                suggestionsDropdown.classList.add('hidden');
            }
        });

        // Show suggestions again on focus if there is value
        searchInput.addEventListener('focus', async () => {
            const query = searchInput.value.trim();
            if (query.length > 0) {
                try {
                    const suggestions = await window.api.getSuggestions(query);
                    this.renderSuggestions(suggestions);
                } catch (err) {}
            }
        });

        document.getElementById('logo').addEventListener('click', () => {
            document.getElementById('searchInput').value = '';
            suggestionsDropdown.classList.add('hidden');
            this.loadTrending();
        });

        document.getElementById('downloadMp4Btn').addEventListener('click', () => {
            if (!currentVideo) return;
            const quality = document.getElementById('mp4Quality').value;
            ui.showToast('Starting MP4 Download...', 'success');
            window.location.href = window.api.getMp4Url(currentVideo.id, quality);
        });

        document.getElementById('downloadMp3Btn').addEventListener('click', () => {
            if (!currentVideo) return;
            const quality = document.getElementById('mp3Quality').value;
            ui.showToast('Starting MP3 Download...', 'success');
            window.location.href = window.api.getMp3Url(currentVideo.id, quality);
        });
    },

    async loadTrending() {
        ui.switchView('homeView');
        ui.renderSkeletons();
        try {
            // "trend" or empty query could just search "latest music" or similar for default
            const results = await window.api.searchVideos("trending");
            ui.renderVideos(results);
        } catch (error) {
            ui.showToast('Failed to load videos', 'error');
            document.getElementById('videoGrid').innerHTML = '<div class="col-span-full text-center text-red-400 py-10">Error loading videos. Make sure backend is running.</div>';
        }
    },

    async performSearch(query) {
        ui.switchView('homeView');
        ui.renderSkeletons();
        try {
            const results = await window.api.searchVideos(query);
            ui.renderVideos(results);
        } catch (error) {
            ui.showToast('Search failed', 'error');
        }
    },

    async openVideo(id) {
        currentVideo = { id };
        ui.switchView('videoView');
        window.scrollTo(0, 0);
        
        // Initial setup with basic info if passed, or fetch
        document.getElementById('videoTitle').textContent = 'Loading...';
        document.getElementById('videoChannel').textContent = '';
        document.getElementById('videoStats').textContent = '';
        
        const player = document.getElementById('player');
        
        try {
            // Load details
            const details = await window.api.getVideoDetails(id);
            currentVideo = details;
            
            document.getElementById('videoTitle').textContent = details.title;
            document.getElementById('videoChannel').textContent = details.uploader || 'Unknown Channel';
            document.getElementById('videoStats').textContent = `${ui.formatViews(details.view_count || 0)} views • ${details.upload_date || ''}`;
            document.getElementById('channelAvatar').textContent = (details.uploader || 'C').charAt(0).toUpperCase();
            
            // Set stream source
            player.src = window.api.getStreamUrl(id);
            player.play().catch(e => console.log('Autoplay prevented', e));
            
        } catch (error) {
            ui.showToast('Failed to load video details', 'error');
        }
    },
    
    renderSuggestions(suggestions) {
        const dropdown = document.getElementById('suggestionsDropdown');
        if (!suggestions || suggestions.length === 0) {
            dropdown.classList.add('hidden');
            return;
        }

        dropdown.innerHTML = '';
        suggestions.forEach(item => {
            const row = document.createElement('div');
            row.className = 'px-4 py-2.5 hover:bg-slate-700 cursor-pointer flex items-center space-x-2 text-slate-200 transition-colors border-b border-slate-700/50 last:border-0';
            row.innerHTML = `
                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                <span>${item}</span>
            `;
            row.addEventListener('click', () => {
                const searchInput = document.getElementById('searchInput');
                searchInput.value = item;
                dropdown.classList.add('hidden');
                this.performSearch(item);
            });
            dropdown.appendChild(row);
        });

        dropdown.classList.remove('hidden');
    },

    registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js').then(registration => {
                    console.log('SW registered: ', registration);
                }).catch(registrationError => {
                    console.log('SW registration failed: ', registrationError);
                });
            });
        }
    }
};

window.app = app;

// Initialize app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    app.init();
});
