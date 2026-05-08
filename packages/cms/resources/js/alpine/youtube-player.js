document.addEventListener('alpine:init', () => {
    Alpine.data('youtube_player', () => ({
        videoId: '',
        videoUrl: '',
        aspectMode: 'landscape',

        extractVideoId(url) {
            if (!url) {
                return null;
            }

            const patterns = [
                /(?:youtube\.com\/(?:watch\?.*v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/,
            ];

            for (const pattern of patterns) {
                const match = url.match(pattern);
                if (match) {
                    return match[1];
                }
            }

            return null;
        },

        detectAspectMode(url) {
            if (!url) {
                return 'landscape';
            }

            return /(?:youtube\.com\/shorts\/|youtu\.be\/shorts\/)/i.test(url)
                ? 'portrait'
                : 'landscape';
        },

        setAspectMode(mode) {
            if (!['portrait', 'landscape'].includes(mode)) {
                return;
            }

            this.aspectMode = mode;
        },

        resetPlayer() {
            this.videoId = '';
            this.videoUrl = '';
            this.aspectMode = 'landscape';
        },

        frameClass() {
            return this.aspectMode === 'portrait'
                ? 'aspect-[9/16] max-w-sm sm:max-w-md'
                : 'aspect-video max-w-full';
        },

        openVideo(url) {
            const id = this.extractVideoId(url);
            if (!id) {
                if (url) {
                    window.open(url, '_blank');
                }
                return;
            }

            this.videoUrl = url;
            this.videoId = id;
            this.aspectMode = this.detectAspectMode(url);
            Flux.modal('youtube-player').show();
        },

        openOnYouTube() {
            if (!this.videoUrl) {
                return;
            }

            Flux.modal('youtube-player').close();
            window.open(this.videoUrl, '_blank', 'noopener,noreferrer');
        },
    }));
});
