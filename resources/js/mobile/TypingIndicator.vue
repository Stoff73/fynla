<template>
  <div class="flex justify-start">
    <img
      src="/images/logos/favicon.png"
      alt="Fyn"
      class="w-7 h-7 rounded-full flex-shrink-0 mt-1 mr-2"
    />
    <div class="bg-white rounded-2xl rounded-bl-sm shadow-sm px-5 py-3.5 flex items-center gap-1.5">
      <span class="typing-dot w-2 h-2 rounded-full bg-neutral-400"></span>
      <span class="typing-dot w-2 h-2 rounded-full bg-neutral-400 animation-delay-150"></span>
      <span class="typing-dot w-2 h-2 rounded-full bg-neutral-400 animation-delay-300"></span>
    </div>
  </div>
</template>

<script>
export default {
  name: 'TypingIndicator',
};
</script>

<style scoped>
.typing-dot {
  animation: typing-bounce 1.4s infinite ease-in-out;
}

.animation-delay-150 {
  animation-delay: 0.15s;
}

.animation-delay-300 {
  animation-delay: 0.3s;
}

@keyframes typing-bounce {
  0%, 60%, 100% {
    transform: translateY(0);
    opacity: 0.4;
  }
  30% {
    transform: translateY(-4px);
    opacity: 1;
  }
}
</style>
