<template>
  <button
    class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center transition-all"
    :class="isListening
      ? 'bg-raspberry-500 text-white voice-pulse'
      : 'bg-eggshell-500 text-neutral-500 active:bg-savannah-100'"
    @click="toggleListening"
  >
    <!-- Mic icon (not listening) -->
    <svg v-if="!isListening" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round"
        d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4M12 15a3 3 0 003-3V5a3 3 0 00-6 0v7a3 3 0 003 3z" />
    </svg>

    <!-- Waveform animation (listening) -->
    <div v-else class="flex items-center gap-0.5">
      <span class="voice-bar w-0.5 h-3 bg-white rounded-full"></span>
      <span class="voice-bar w-0.5 h-4 bg-white rounded-full animation-delay-100"></span>
      <span class="voice-bar w-0.5 h-2.5 bg-white rounded-full animation-delay-200"></span>
      <span class="voice-bar w-0.5 h-4 bg-white rounded-full animation-delay-300"></span>
      <span class="voice-bar w-0.5 h-3 bg-white rounded-full animation-delay-150"></span>
    </div>
  </button>
</template>

<script>
export default {
  name: 'VoiceInputButton',

  emits: ['transcript', 'partial'],

  data() {
    return {
      isListening: false,
      recognition: null,
      useNative: false,
      lastPartial: '',
    };
  },

  async mounted() {
    await this.initRecognition();
  },

  beforeUnmount() {
    this.forceStop();
  },

  methods: {
    async initRecognition() {
      // Try Capacitor Speech Recognition plugin first
      try {
        const { SpeechRecognition } = await import('@capacitor-community/speech-recognition');
        const { available } = await SpeechRecognition.available();
        if (available) {
          this.useNative = true;
          return;
        }
      } catch {
        // Plugin not available, fall through to Web Speech API
      }

      // Web Speech API fallback
      const SpeechRecognitionClass = window.SpeechRecognition || window.webkitSpeechRecognition;
      if (SpeechRecognitionClass) {
        const recognition = new SpeechRecognitionClass();
        recognition.lang = 'en-GB';
        recognition.interimResults = true;
        recognition.continuous = true;

        recognition.onresult = (event) => {
          let finalTranscript = '';
          let interimTranscript = '';

          for (let i = event.resultIndex; i < event.results.length; i++) {
            const transcript = event.results[i][0].transcript;
            if (event.results[i].isFinal) {
              finalTranscript += transcript;
            } else {
              interimTranscript += transcript;
            }
          }

          if (interimTranscript) {
            this.$emit('partial', interimTranscript);
          }
          if (finalTranscript) {
            this.$emit('transcript', finalTranscript);
          }
        };

        // Auto-restart on end/error if still in listening mode
        recognition.onend = () => {
          if (this.isListening) {
            try { recognition.start(); } catch { /* ignore */ }
          }
        };

        recognition.onerror = () => {
          if (this.isListening) {
            setTimeout(() => {
              if (this.isListening) {
                try { recognition.start(); } catch { /* ignore */ }
              }
            }, 500);
          }
        };

        this.recognition = recognition;
      }
    },

    toggleListening() {
      if (this.isListening) {
        this.deactivate();
      } else {
        this.activate();
      }
    },

    async activate() {
      if (this.isListening) return;
      this.isListening = true;
      this.lastPartial = '';

      if (this.useNative) {
        try {
          const { SpeechRecognition } = await import('@capacitor-community/speech-recognition');

          // Check/request permissions
          const { speechRecognition } = await SpeechRecognition.checkPermissions();
          if (speechRecognition !== 'granted') {
            const result = await SpeechRecognition.requestPermissions();
            if (result.speechRecognition !== 'granted') {
              this.isListening = false;
              return;
            }
            // Give iOS a moment after fresh permission grant
            await new Promise(r => setTimeout(r, 500));
            if (!this.isListening) return;
          }

          await this.startNativeSession();
        } catch {
          this.isListening = false;
        }
      } else if (this.recognition) {
        try {
          this.recognition.start();
        } catch {
          this.isListening = false;
        }
      }
    },

    async startNativeSession() {
      if (!this.isListening) return;

      const { SpeechRecognition } = await import('@capacitor-community/speech-recognition');

      // Remove old listeners before adding new ones
      await SpeechRecognition.removeAllListeners();

      // Partial results — fires for both interim and final transcriptions
      SpeechRecognition.addListener('partialResults', (data) => {
        if (data.matches && data.matches.length > 0) {
          this.lastPartial = data.matches[0];
          this.$emit('partial', data.matches[0]);
        }
      });

      // Listening state — fires when recognition session ends (silence timeout or error)
      // This is the ONLY place we restart for continuous listening
      SpeechRecognition.addListener('listeningState', (data) => {
        if (data.status === 'stopped' && this.isListening) {
          // Don't emit transcript here — text is already in input from partial events
          this.lastPartial = '';
          // Auto-restart after a delay for continuous listening
          setTimeout(() => {
            if (this.isListening) {
              this.startNativeSession();
            }
          }, 500);
        }
      });

      // start() resolves immediately when partialResults: true
      // Results come through the listeners above, NOT from the promise
      try {
        await SpeechRecognition.start({
          language: 'en-GB',
          partialResults: true,
          popup: false,
        });
      } catch {
        // "Ongoing speech recognition" — previous session still running
        // Wait for listeningState "stopped" to fire, or retry after delay
        setTimeout(() => {
          if (this.isListening) {
            this.startNativeSession();
          }
        }, 1000);
      }
    },

    deactivate() {
      if (!this.isListening) return;
      this.isListening = false;

      // Don't emit transcript — text is already in input from partial events
      this.lastPartial = '';

      this.forceStop();
    },

    forceStop() {
      if (this.useNative) {
        import('@capacitor-community/speech-recognition').then(({ SpeechRecognition }) => {
          // Remove listeners first to prevent ghost restarts
          SpeechRecognition.removeAllListeners();
          SpeechRecognition.stop().catch(() => {});
        }).catch(() => {});
      } else if (this.recognition) {
        try { this.recognition.stop(); } catch { /* ignore */ }
      }
    },
  },
};
</script>

<style scoped>
.voice-pulse {
  animation: voice-pulse 1.5s infinite;
}

/* raspberry-500 (#E83E6D) in rgba — CSS vars unavailable in scoped keyframes */
@keyframes voice-pulse {
  0%, 100% {
    box-shadow: 0 0 0 0 rgba(232, 62, 109, 0.4);
  }
  50% {
    box-shadow: 0 0 0 8px rgba(232, 62, 109, 0);
  }
}

.voice-bar {
  animation: voice-wave 0.8s infinite ease-in-out alternate;
}

.animation-delay-100 {
  animation-delay: 0.1s;
}

.animation-delay-150 {
  animation-delay: 0.15s;
}

.animation-delay-200 {
  animation-delay: 0.2s;
}

.animation-delay-300 {
  animation-delay: 0.3s;
}

@keyframes voice-wave {
  0% {
    transform: scaleY(0.5);
  }
  100% {
    transform: scaleY(1.5);
  }
}
</style>
