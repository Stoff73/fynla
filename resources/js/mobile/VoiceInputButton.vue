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
    };
  },

  async mounted() {
    await this.initRecognition();
  },

  beforeUnmount() {
    this.stopListening();
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
        this.recognition = new SpeechRecognitionClass();
        this.recognition.lang = 'en-GB';
        this.recognition.interimResults = true;
        this.recognition.continuous = false;

        this.recognition.onresult = (event) => {
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
            this.isListening = false;
          }
        };

        this.recognition.onerror = () => {
          this.isListening = false;
        };

        this.recognition.onend = () => {
          this.isListening = false;
        };
      }
    },

    async toggleListening() {
      if (this.isListening) {
        this.stopListening();
      } else {
        await this.startListening();
      }
    },

    async startListening() {
      this.isListening = true;

      if (this.useNative) {
        try {
          const { SpeechRecognition } = await import('@capacitor-community/speech-recognition');

          // Request permission if needed
          const { permission } = await SpeechRecognition.checkPermissions();
          if (permission !== 'granted') {
            await SpeechRecognition.requestPermissions();
          }

          SpeechRecognition.addListener('partialResults', (data) => {
            if (data.matches && data.matches.length > 0) {
              this.$emit('partial', data.matches[0]);
            }
          });

          const result = await SpeechRecognition.start({
            language: 'en-GB',
            partialResults: true,
            popup: false,
          });

          if (result.matches && result.matches.length > 0) {
            this.$emit('transcript', result.matches[0]);
          }
        } catch {
          // Speech recognition failed
        } finally {
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

    stopListening() {
      this.isListening = false;

      if (this.useNative) {
        import('@capacitor-community/speech-recognition').then(({ SpeechRecognition }) => {
          SpeechRecognition.stop();
          SpeechRecognition.removeAllListeners();
        }).catch(() => {});
      } else if (this.recognition) {
        this.recognition.stop();
      }
    },
  },
};
</script>

<style scoped>
.voice-pulse {
  animation: voice-pulse 1.5s infinite;
}

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
