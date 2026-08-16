<template>
    <div class="message-renderer">
        <!-- Text Card -->
        <TextCard v-if="isText" :content="message.content || ''" />

        <!-- Product Single Card -->
        <ProductCard 
            v-else-if="message.type === 'product' && message.payload && message.payload.product" 
            :product="message.payload.product" 
        />

        <!-- Product List Card -->
        <ProductListCard 
            v-else-if="message.type === 'product_list' && message.payload && message.payload.products" 
            :products="message.payload.products" 
        />

        <!-- Order / Order Status Card -->
        <OrderStatusCard 
            v-else-if="(message.type === 'order' || message.type === 'order_status') && (message.payload?.order || message.payload?.order_status || message.payload)" 
            :order="message.payload.order || message.payload.order_status || message.payload" 
        />

        <!-- Action Confirmation Card -->
        <ActionConfirmationCard 
            v-else-if="message.type === 'action_confirmation' && message.payload" 
            :payload="message.payload" 
        />

        <!-- Escalation Card -->
        <EscalationCard 
            v-else-if="message.type === 'escalation'" 
            :content="message.content" 
            :payload="message.payload || {}" 
        />

        <!-- Error Card -->
        <ErrorMessageCard 
            v-else-if="message.type === 'error'" 
            :content="message.content || 'An error occurred processing your request.'" 
        />

        <!-- Fallback to Text -->
        <TextCard v-else :content="message.content || ''" />
    </div>
</template>

<script>
import TextCard from './cards/TextCard.vue';
import ProductCard from './cards/ProductCard.vue';
import ProductListCard from './cards/ProductListCard.vue';
import OrderStatusCard from './cards/OrderStatusCard.vue';
import ActionConfirmationCard from './cards/ActionConfirmationCard.vue';
import EscalationCard from './cards/EscalationCard.vue';
import ErrorMessageCard from './cards/ErrorMessageCard.vue';

export default {
    name: 'MessageRenderer',
    components: {
        TextCard,
        ProductCard,
        ProductListCard,
        OrderStatusCard,
        ActionConfirmationCard,
        EscalationCard,
        ErrorMessageCard,
    },
    props: {
        message: {
            type: Object,
            required: true,
        },
    },
    computed: {
        isText() {
            return this.message.type === 'text' || !this.message.type;
        },
    },
};
</script>
