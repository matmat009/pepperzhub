export const FAQ_CATEGORIES = [
    'All',
    'Ordering',
    'Payment',
    'Shipping',
    'Products',
    'Support',
] as const;

export type FaqCategory = (typeof FAQ_CATEGORIES)[number];
export type QuestionCategory = Exclude<FaqCategory, 'All'>;

export type FaqItem = {
    id: string;
    category: QuestionCategory;
    question: string;
    answer: string;
};

/**
 * One maintainable source for the public FAQ. Policy gaps stay explicit rather
 * than being filled with promises the store has not published.
 */
export const FAQ_ITEMS: FaqItem[] = [
    {
        id: 'place-an-order',
        category: 'Ordering',
        question: 'How do I place an order?',
        answer: 'Add the vials or kits you need to your cart, then continue through checkout. Enter your contact and delivery details, choose a courier and delivery option, select one of the payment methods shown, upload your payment proof, and submit the order. Keep the order number on your confirmation page for tracking and support.',
    },
    {
        id: 'minimum-order',
        category: 'Ordering',
        question: 'Is there a minimum order?',
        answer: 'A minimum-order policy is not currently published. Add the items you need and review the cart and checkout totals, or contact support before ordering if you need the policy confirmed.',
    },
    {
        id: 'payment-methods',
        category: 'Payment',
        question: 'What payment methods do you accept?',
        answer: 'The payment methods currently available are shown at checkout. Select a method there to view its current account details and QR code, where provided.',
    },
    {
        id: 'payment-confirmed',
        category: 'Payment',
        question: 'When is my payment confirmed?',
        answer: 'Uploading proof submits your order for manual review; it does not confirm payment. Payment is confirmed only after the store verifies the transfer. Use Track Order with your order number and phone number for the latest status.',
    },
    {
        id: 'cash-on-delivery',
        category: 'Payment',
        question: 'Do you offer cash on delivery?',
        answer: 'Cash on delivery availability is not currently published. Use only the payment methods shown at checkout, or contact support before ordering if you need this confirmed.',
    },
    {
        id: 'delivery-time',
        category: 'Shipping',
        question: 'How long does delivery take?',
        answer: 'Delivery timing depends on the selected courier, delivery option, and destination. No fixed delivery promise is currently published, so contact support with your location or order number for current guidance.',
    },
    {
        id: 'store-a-vial',
        category: 'Products',
        question: 'How should I store a vial?',
        answer: 'Follow the storage instructions shown on the relevant product page and packaging. Requirements can vary by product, so contact support if the instructions are missing or unclear. Do not apply one product’s storage guidance to another.',
    },
    {
        id: 'intended-use',
        category: 'Products',
        question: 'What is the intended use of these compounds?',
        answer: 'These compounds are supplied for laboratory research use only. They are not for human consumption.',
    },
    {
        id: 'change-or-cancel',
        category: 'Support',
        question: 'Can I change or cancel an order?',
        answer: 'Contact support as soon as possible with your order number. Whether a change or cancellation is possible depends on the order’s current status; no general cancellation policy is currently published.',
    },
    {
        id: 'damaged-order',
        category: 'Support',
        question: 'What if something arrives damaged?',
        answer: 'Contact support promptly with your order number and clear photos of the parcel and item. The store will review the case; no automatic refund or replacement guarantee is currently published.',
    },
];
