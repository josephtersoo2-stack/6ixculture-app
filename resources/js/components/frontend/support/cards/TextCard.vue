<template>
    <div class="support-text-card text-sm leading-relaxed text-slate-800 whitespace-pre-wrap">
        {{ content }}
    </div>
</template>

<script>
export default {
    name: 'TextCard',
    props: {
        content: {
            type: String,
            required: true,
        },
    },
};
</script>
