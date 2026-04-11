document.addEventListener('alpine:init', () => {
    Alpine.data('youtube_player', () => ({
        videoId: '',
        videoUrl: '',

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
