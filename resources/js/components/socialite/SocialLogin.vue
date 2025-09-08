<script setup lang="ts">
import { Button } from '@/components/ui/button';

defineProps<{
  mode?: 'login' | 'register' | 'connect'
}>();

// Social login providers
const providers = [
  {
    name: 'Google',
    icon: 'i-lucide-google',
    route: (mode: string) => mode === 'connect' ? 'socialite.connect' : 'socialite.redirect',
    provider: 'google',
    class: 'bg-white hover:bg-gray-100 text-black border border-gray-300'
  },
  {
    name: 'Facebook',
    icon: 'i-lucide-facebook',
    route: (mode: string) => mode === 'connect' ? 'socialite.connect' : 'socialite.redirect',
    provider: 'facebook',
    class: 'bg-blue-600 hover:bg-blue-700 text-white'
  },
  {
    name: 'GitHub',
    icon: 'i-lucide-github',
    route: (mode: string) => mode === 'connect' ? 'socialite.connect' : 'socialite.redirect',
    provider: 'github',
    class: 'bg-gray-900 hover:bg-gray-800 text-white'
  }
];
</script>

<template>
  <div class="flex flex-col gap-3 w-full">
    <div class="relative flex items-center justify-center text-xs uppercase my-2">
      <div class="flex-grow border-t"></div>
      <span class="flex-shrink mx-4 text-muted-foreground">Or continue with</span>
      <div class="flex-grow border-t"></div>
    </div>

    <div class="grid grid-cols-1 gap-2">
      <a 
        v-for="provider in providers" 
        :key="provider.name" 
        :href="route(provider.route(mode || 'login'), { provider: provider.provider })"
        :class="[provider.class, 'flex items-center justify-center gap-2 rounded-md py-2 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring w-full']"
      >
        <div :class="[provider.icon, 'w-5 h-5']"></div>
        <span>{{ mode === 'connect' ? `Connect with ${provider.name}` : `Sign in with ${provider.name}` }}</span>
      </a>
    </div>
  </div>
</template>