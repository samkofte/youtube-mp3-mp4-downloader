/* ui.js */

const ui = {
    showToast(message, type = 'success') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        
        const bgColor = type === 'success' ? 'bg-emerald-600' : (type === 'error' ? 'bg-red-600' : 'bg-slate-700');
        
        toast.className = `toast-enter ${bgColor} text-white px-4 py-3 rounded-lg shadow-lg flex items-center space-x-2 min-w-[200px] max-w-sm`;
        
        const icon = type === 'success' 
            ? '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>'
            : '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
            
        toast.innerHTML = `
            ${icon}
            <span class="font-medium">${message}</span>
        `;
        
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideInRight 0.3s ease-in reverse forwards';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    },

    renderSkeletons(count = 8) {
        const grid = document.getElementById('videoGrid');
        grid.innerHTML = '';
        for (let i = 0; i < count; i++) {
            grid.innerHTML += `
                <div class="bg-slate-800 rounded-xl overflow-hidden shadow-lg border border-slate-700/50">
                    <div class="w-full aspect-video skeleton"></div>
                    <div class="p-4 flex space-x-3">
                        <div class="w-10 h-10 rounded-full skeleton flex-shrink-0"></div>
                        <div class="flex-1 space-y-2 py-1">
                            <div class="h-4 skeleton rounded w-3/4"></div>
                            <div class="h-3 skeleton rounded w-1/2"></div>
                            <div class="h-3 skeleton rounded w-1/3"></div>
                        </div>
                    </div>
                </div>
            `;
        }
    },

    renderVideos(videos) {
        const grid = document.getElementById('videoGrid');
        grid.innerHTML = '';
        
        if (videos.length === 0) {
            grid.innerHTML = '<div class="col-span-full text-center text-slate-400 py-10">No videos found.</div>';
            return;
        }

        videos.forEach(video => {
            const card = document.createElement('div');
            card.className = 'bg-slate-800 rounded-xl overflow-hidden shadow-lg border border-slate-700/50 cursor-pointer hover:scale-[1.02] hover:shadow-primary/20 transition-all duration-300 group';
            card.onclick = () => window.app.openVideo(video.id);
            
            card.innerHTML = `
                <div class="relative w-full aspect-video bg-black">
                    <img src="${video.thumbnail}" alt="${video.title}" class="w-full h-full object-cover group-hover:opacity-80 transition-opacity">
                    <span class="absolute bottom-2 right-2 bg-black/80 text-white text-xs px-1.5 py-0.5 rounded">${video.duration}</span>
                </div>
                <div class="p-4 flex space-x-3">
                    <div class="w-10 h-10 rounded-full bg-slate-700 flex items-center justify-center font-bold text-slate-300 flex-shrink-0 uppercase">
                        ${video.channel ? video.channel.charAt(0) : 'C'}
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-white font-semibold line-clamp-2 leading-tight group-hover:text-primary transition-colors">${video.title}</h3>
                        <p class="text-slate-400 text-sm mt-1 truncate">${video.channel}</p>
                        <p class="text-slate-500 text-xs mt-0.5">${video.views ? this.formatViews(video.views) + ' views' : ''}</p>
                    </div>
                </div>
            `;
            grid.appendChild(card);
        });
    },

    formatViews(num) {
        if (num >= 1e9) return (num / 1e9).toFixed(1) + 'B';
        if (num >= 1e6) return (num / 1e6).toFixed(1) + 'M';
        if (num >= 1e3) return (num / 1e3).toFixed(1) + 'K';
        return num.toString();
    },

    switchView(viewName) {
        document.getElementById('homeView').classList.add('hidden');
        document.getElementById('videoView').classList.add('hidden');
        document.getElementById(viewName).classList.remove('hidden');
        
        if (viewName !== 'videoView') {
            const player = document.getElementById('player');
            player.pause();
            player.src = '';
        }
    }
};

window.ui = ui;
