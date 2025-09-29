// tailwind.config.js
export default {
    content: [
        "./resources/**/*.blade.php",
        "./resources/**/*.js",
        "./resources/**/*.vue",    // Vue使わないなら削除可
    ],
    theme: {
        extend: {},
    },
    plugins: [
        // require('@tailwindcss/forms'),
        // require('@tailwindcss/typography'),
        // require('@tailwindcss/aspect-ratio'),
        // require('@tailwindcss/line-clamp'),
    ],
}
