    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
/*
    Mobil sidebar aç/kapat
*/
const sidebar = document.querySelector('.sidebar');
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebarOverlay = document.getElementById('sidebarOverlay');

if (sidebarToggle && sidebar && sidebarOverlay) {
    sidebarToggle.addEventListener('click', function () {
        sidebar.classList.toggle('show');
        sidebarOverlay.classList.toggle('show');
        document.body.classList.toggle('menu-open');
    });

    sidebarOverlay.addEventListener('click', function () {
        sidebar.classList.remove('show');
        sidebarOverlay.classList.remove('show');
        document.body.classList.remove('menu-open');
    });

    const sidebarLinks = document.querySelectorAll('.sidebar-menu a');

    sidebarLinks.forEach(function (link) {
        link.addEventListener('click', function () {
            if (window.innerWidth <= 992) {
                sidebar.classList.remove('show');
                sidebarOverlay.classList.remove('show');
                document.body.classList.remove('menu-open');
            }
        });
    });
}
</script>

<script>
/*
    Sisteme giriş yapınca bir kere hoş geldiniz sesi
    Bu kod header.php içindeki window.playWelcomeVoice değişkenine göre çalışır.
*/
function speakWelcomeAfterLogin() {
    if (!window.playWelcomeVoice) {
        return;
    }

    if (!('speechSynthesis' in window)) {
        return;
    }

    const speakMessage = function () {
        window.speechSynthesis.cancel();

        const message = new SpeechSynthesisUtterance('Otelimize hoş geldiniz');
        message.lang = 'tr-TR';
        message.rate = 0.9;
        message.pitch = 1;
        message.volume = 1;

        const voices = window.speechSynthesis.getVoices();

        const turkishVoice = voices.find(function (voice) {
            return voice.lang === 'tr-TR' || voice.lang.startsWith('tr');
        });

        if (turkishVoice) {
            message.voice = turkishVoice;
        }

        setTimeout(function () {
            window.speechSynthesis.speak(message);
        }, 700);
    };

    const voices = window.speechSynthesis.getVoices();

    if (voices.length > 0) {
        speakMessage();
    } else {
        window.speechSynthesis.onvoiceschanged = function () {
            speakMessage();
            window.speechSynthesis.onvoiceschanged = null;
        };
    }
}

window.addEventListener('load', function () {
    speakWelcomeAfterLogin();
});
</script>

</body>
</html>