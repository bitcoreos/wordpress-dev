<!-- BitFlux State Controller -->
<script>
var currentState = 'welcome';
var isSpinning = false;
var spinInterval = null;

function setBitFluxState(state) {
    if (isSpinning && state !== 'spin') {
        stopSpin();
    }

    if (state === 'spin') {
        toggleSpin();
        return;
    }

    currentState = state;

    var wrapper = document.getElementById('bitflux-avatar');
    var label = document.getElementById('bitflux-state-text');
    var svg = document.getElementById('bitflux-svg');

    if (wrapper) wrapper.dataset.state = state;
    if (label) label.textContent = state.toUpperCase().replace('-', ' ');

    // Update button states
    document.querySelectorAll('.state-btn').forEach(function(btn) {
        btn.classList.remove('active');
        if (btn.dataset.state === state) {
            btn.classList.add('active');
        }
    });

    // Update SVG
    if (svg && svg.contentDocument && svg.contentDocument.documentElement) {
        var root = svg.contentDocument.documentElement;
        root.classList.remove(
            'bitflux-active-welcome', 'bitflux-active-listen', 'bitflux-active-chat-ready',
            'bitflux-active-focused-shield', 'bitflux-active-agent-task', 'bitflux-active-process',
            'bitflux-active-swarm-guard', 'bitflux-active-crown-mode', 'bitflux-active-portal-enter',
            'bitflux-active-error-404'
        );
        root.classList.add('bitflux-active-' + state);
    }
}

function toggleSpin() {
    if (isSpinning) {
        stopSpin();
    } else {
        startSpin();
    }
}

function startSpin() {
    isSpinning = true;
    var wrapper = document.getElementById('bitflux-avatar');
    var label = document.getElementById('bitflux-state-text');
    var spinBtn = document.querySelector('.spin-btn');

    if (wrapper) wrapper.classList.add('bitflux-spinning');
    if (spinBtn) {
        spinBtn.classList.add('active');
        spinBtn.textContent = '⏹';
    }

    var states = ['welcome', 'listen', 'chat-ready', 'focused-shield', 'agent-task', 
                  'process', 'swarm-guard', 'crown-mode', 'portal-enter', 'error-404'];
    var index = 0;

    spinInterval = setInterval(function() {
        if (!isSpinning) return;
        var state = states[index % states.length];
        setBitFluxState(state);
        if (label) label.textContent = state.toUpperCase().replace('-', ' ') + ' (SPIN)';
        index++;
    }, 600);
}

function stopSpin() {
    isSpinning = false;
    clearInterval(spinInterval);

    var wrapper = document.getElementById('bitflux-avatar');
    var spinBtn = document.querySelector('.spin-btn');

    if (wrapper) wrapper.classList.remove('bitflux-spinning');
    if (spinBtn) {
        spinBtn.classList.remove('active');
        spinBtn.textContent = '↻';
    }

    setBitFluxState('welcome');
}

// Initialize when SVG loads
document.getElementById('bitflux-svg').addEventListener('load', function() {
    setBitFluxState('welcome');
});

// Form submission
document.getElementById('bitcore-submit')?.addEventListener('click', function(e) {
    e.preventDefault();
    var input = document.getElementById('bitcore-intent-input').value;
    if (input.trim()) {
        setBitFluxState('agent-task');
        console.log('Submitting:', input);
    }
});
</script>
