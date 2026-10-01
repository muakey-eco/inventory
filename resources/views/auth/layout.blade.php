{{-- Khung chung của các trang đăng nhập tĩnh. Không có JS, không tự chuyển hướng. --}}
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: light dark; --bg: #f8fafc; --card: #fff; --text: #0f172a; --muted: #475569; --accent: #b45309; --border: #e2e8f0; }
        @media (prefers-color-scheme: dark) { :root { --bg: #0f172a; --card: #1e293b; --text: #f1f5f9; --muted: #94a3b8; --accent: #f59e0b; --border: #334155; } }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: var(--bg); color: var(--text); font: 16px/1.5 system-ui, sans-serif; }
        main { width: min(28rem, calc(100% - 2rem)); padding: 2rem; background: var(--card); border: 1px solid var(--border); border-radius: .75rem; }
        h1 { margin: 0 0 .75rem; font-size: 1.25rem; }
        p { margin: 0 0 1.25rem; color: var(--muted); }
        a, button { color: var(--accent); font: inherit; font-weight: 600; }
        ul { list-style: none; margin: 0; padding: 0; }
        li { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: .5rem 0; border-top: 1px solid var(--border); }
        button { background: none; border: 0; padding: 0; cursor: pointer; }
        small { color: var(--muted); }
    </style>
</head>
<body>
    <main>
        @yield('content')
    </main>
</body>
</html>
