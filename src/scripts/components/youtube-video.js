import { loadScript } from '../utilities';

const SRC = 'https://www.youtube.com/iframe_api';

export default function YoutubeVideo(el, {
    videoId,
    actions,
    events,
}) {
    function initPlayer() {
        const player = new YT.Player(el, { // eslint-disable-line no-undef
            videoId,
            playerVars: {
                autoplay: 1,
                rel: 0,
            },
        });

        events.on(actions.openModal, () => {
            if (player.playVideo) player.playVideo();
        });
        events.on(actions.closeModal, () => {
            if (player.pauseVideo) player.pauseVideo();
        });
        player.addEventListener('onReady', () => {
            player.playVideo();
        });
    }

    if (!document.querySelector(`[src="${SRC}"]`)) {
        window.onYouTubeIframeAPIReady = initPlayer;
        loadScript(SRC);
    } else {
        initPlayer();
    }
}
