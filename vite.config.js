import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/filament/admin/theme.css'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        // Vite chạy trong container (service `node`), nên phải nghe ra ngoài loopback của container.
        // `origin` là địa chỉ trình duyệt dùng để gọi vite — đổi bằng VITE_ORIGIN khi app không mở
        // ở localhost.
        host: '0.0.0.0',
        port: 5173,
        origin: process.env.VITE_ORIGIN ?? 'http://localhost:5173',
        // Origin của trang app được nạp script từ vite. Phải khai tường minh: có `origin` ở trên thì
        // laravel-vite-plugin lấy chính nó (origin của vite) làm danh sách CORS, và trình duyệt chặn
        // `@vite/client` khi panel nạp theme.css qua vite.
        cors: {
            origin: process.env.VITE_APP_ORIGIN ?? 'http://localhost:8080',
        },
        hmr: {
            host: process.env.VITE_HMR_HOST ?? 'localhost',
            // Cổng trình duyệt dùng để mở websocket HMR. Đổi khi cổng 5173 trên host đã bị chiếm
            // và vite được map ra một cổng khác.
            clientPort: Number(process.env.VITE_HMR_CLIENT_PORT ?? 5173),
        },
        watch: {
            // Vite đăng ký một inotify watch cho mỗi file. Trong container, vendor/ (hàng chục
            // nghìn file của Filament) làm cạn hạn mức của host và giết dev server bằng ENOSPC.
            ignored: [
                '**/storage/**',
                '**/vendor/**',
                '**/node_modules/**',
                '**/.git/**',
            ],
        },
    },
});
