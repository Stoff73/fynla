<template>
    <div class="animate-pulse space-y-6 p-6">
        <!-- Header skeleton -->
        <div class="flex items-center justify-between">
            <div class="space-y-2">
                <div class="h-8 w-48 bg-gray-200 rounded"></div>
                <div class="h-4 w-32 bg-gray-200 rounded"></div>
            </div>
            <div class="h-10 w-10 bg-gray-200 rounded-full"></div>
        </div>

        <!-- Stats cards skeleton -->
        <div v-if="type === 'dashboard'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div v-for="i in 4" :key="i" class="bg-white rounded-lg shadow p-4">
                <div class="h-4 w-20 bg-gray-200 rounded mb-2"></div>
                <div class="h-8 w-24 bg-gray-200 rounded"></div>
            </div>
        </div>

        <!-- Chart skeleton -->
        <div v-if="type === 'dashboard' || type === 'chart'" class="bg-white rounded-lg shadow p-4">
            <div class="h-6 w-32 bg-gray-200 rounded mb-4"></div>
            <div class="h-64 bg-gray-100 rounded flex items-center justify-center">
                <svg class="w-12 h-12 text-gray-200" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z" />
                </svg>
            </div>
        </div>

        <!-- List skeleton -->
        <div v-if="type === 'list'" class="bg-white rounded-lg shadow">
            <div v-for="i in rows" :key="i" class="p-4 border-b last:border-b-0">
                <div class="flex items-center gap-4">
                    <div class="h-12 w-12 bg-gray-200 rounded-full"></div>
                    <div class="flex-1 space-y-2">
                        <div class="h-4 w-3/4 bg-gray-200 rounded"></div>
                        <div class="h-3 w-1/2 bg-gray-200 rounded"></div>
                    </div>
                    <div class="h-8 w-20 bg-gray-200 rounded"></div>
                </div>
            </div>
        </div>

        <!-- Card grid skeleton -->
        <div v-if="type === 'cards'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div v-for="i in 6" :key="i" class="bg-white rounded-lg shadow p-4">
                <div class="h-4 w-24 bg-gray-200 rounded mb-3"></div>
                <div class="space-y-2">
                    <div class="h-3 w-full bg-gray-200 rounded"></div>
                    <div class="h-3 w-5/6 bg-gray-200 rounded"></div>
                    <div class="h-3 w-4/6 bg-gray-200 rounded"></div>
                </div>
                <div class="mt-4 flex justify-end">
                    <div class="h-8 w-16 bg-gray-200 rounded"></div>
                </div>
            </div>
        </div>

        <!-- Form skeleton -->
        <div v-if="type === 'form'" class="bg-white rounded-lg shadow p-6 space-y-4">
            <div v-for="i in 4" :key="i" class="space-y-2">
                <div class="h-4 w-24 bg-gray-200 rounded"></div>
                <div class="h-10 w-full bg-gray-200 rounded"></div>
            </div>
            <div class="flex justify-end gap-2 pt-4">
                <div class="h-10 w-24 bg-gray-200 rounded"></div>
                <div class="h-10 w-24 bg-gray-200 rounded"></div>
            </div>
        </div>

        <!-- Profile skeleton -->
        <div v-if="type === 'profile'" class="bg-white rounded-lg shadow p-6">
            <div class="flex items-start gap-6">
                <div class="h-24 w-24 bg-gray-200 rounded-full"></div>
                <div class="flex-1 space-y-3">
                    <div class="h-6 w-48 bg-gray-200 rounded"></div>
                    <div class="h-4 w-32 bg-gray-200 rounded"></div>
                    <div class="h-4 w-40 bg-gray-200 rounded"></div>
                </div>
            </div>
            <div class="mt-6 grid grid-cols-2 gap-4">
                <div v-for="i in 4" :key="i" class="space-y-2">
                    <div class="h-4 w-20 bg-gray-200 rounded"></div>
                    <div class="h-5 w-full bg-gray-200 rounded"></div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'PreviewLoadingSkeleton',

    props: {
        type: {
            type: String,
            default: 'dashboard',
            validator: (value) => ['dashboard', 'list', 'cards', 'form', 'profile', 'chart'].includes(value),
        },
        rows: {
            type: Number,
            default: 5,
        },
    },
};
</script>
