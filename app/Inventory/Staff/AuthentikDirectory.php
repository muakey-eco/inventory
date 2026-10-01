<?php

namespace App\Inventory\Staff;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Đọc người dùng từ REST API của Authentik bằng token của một service account chỉ đọc (ADR 0008).
 * Mọi câu trả lời lệch dạng đều thành {@see AuthentikUnavailable}: thiếu một người trong kết quả
 * nghĩa là "người đó không còn", nên chỉ được tin kết quả khi đọc trọn vẹn mọi trang.
 */
class AuthentikDirectory
{
    private const PAGE_SIZE = 100;

    private const TIMEOUT_SECONDS = 10;

    /**
     * Mỗi uuid một lần gọi: bộ lọc `uuid` của Authentik là `UUIDFilter` một giá trị, gửi
     * `uuid=a&uuid=b` thì nó chỉ lọc theo uuid cuối và mọi người còn lại trông như đã bị xoá.
     * Shop vài chục người nên vài chục lần gọi mỗi phút là rẻ.
     *
     * @param  list<string>  $uuids
     * @return array<string, AuthentikUser> theo uuid; uuid không có trong mảng là Authentik trả
     *                                      lời rõ rằng người dùng đó không tồn tại
     *
     * @throws AuthentikUnavailable
     */
    public function find(array $uuids): array
    {
        $token = config('services.authentik.api_token');

        if (! is_string($token) || $token === '') {
            throw new AuthentikUnavailable('Chưa cấu hình AUTHENTIK_API_TOKEN.');
        }

        $found = [];

        foreach ($uuids as $uuid) {
            $page = 1;

            // Lọc theo một uuid thì chỉ có một trang; vẫn đi theo `next` cho tới hết, để kể cả
            // khi Authentik bỏ qua bộ lọc thì kết quả vẫn trọn vẹn và vẫn khớp đúng theo uuid.
            do {
                [$results, $next] = $this->page($token, $uuid, $page);

                foreach ($results as $user) {
                    $found[$user->uuid] = $user;
                }

                // Trang kế phải tiến lên, kẻo câu trả lời lệch làm vòng lặp chạy mãi.
                if ($next !== 0 && $next <= $page) {
                    throw new AuthentikUnavailable("Authentik trả trang kế {$next} sau trang {$page}.");
                }

                $page = $next;
            } while ($page !== 0);
        }

        return $found;
    }

    /**
     * @return array{list<AuthentikUser>, int}
     *
     * @throws AuthentikUnavailable
     */
    private function page(string $token, string $uuid, int $page): array
    {
        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(self::TIMEOUT_SECONDS)
                ->get(self::baseUrl().'/api/v3/core/users/', [
                    'uuid' => $uuid,
                    // Mặc định đã là true; ghi rõ vì đối soát không chạy được nếu thiếu groups_obj.
                    'include_groups' => 'true',
                    'page' => $page,
                    'page_size' => self::PAGE_SIZE,
                ])
                ->throw();
        } catch (ConnectionException $exception) {
            throw new AuthentikUnavailable('Không kết nối được Authentik: '.Str::limit($exception->getMessage(), 200), previous: $exception);
        } catch (RequestException $exception) {
            $status = $exception->response->status();

            throw new AuthentikUnavailable(in_array($status, [401, 403], true)
                ? "Authentik từ chối token (HTTP {$status}): kiểm tra AUTHENTIK_API_TOKEN và quyền của service account."
                : "Authentik trả lỗi HTTP {$status}.", previous: $exception);
        }

        $results = $response->json('results');
        $next = $response->json('pagination.next');

        if (! is_array($results) || ! is_int($next)) {
            throw new AuthentikUnavailable('Authentik trả dữ liệu không đúng dạng danh sách người dùng.');
        }

        return [array_values(array_map(self::user(...), $results)), $next];
    }

    /**
     * @throws AuthentikUnavailable
     */
    private static function user(mixed $user): AuthentikUser
    {
        if (! is_array($user) || ! is_string($user['uuid'] ?? null) || ! is_bool($user['is_active'] ?? null) || ! is_array($user['groups_obj'] ?? null)) {
            throw new AuthentikUnavailable('Authentik trả một người dùng thiếu uuid, is_active hoặc groups_obj.');
        }

        $groups = array_map(fn (mixed $group): mixed => is_array($group) ? ($group['name'] ?? null) : null, $user['groups_obj']);
        $name = $user['name'] ?? null;
        $email = $user['email'] ?? null;

        return new AuthentikUser(
            uuid: $user['uuid'],
            name: is_string($name) ? $name : '',
            email: is_string($email) && $email !== '' ? $email : null,
            isActive: $user['is_active'],
            groups: array_values(array_filter($groups, 'is_string')),
        );
    }

    /**
     * API nằm cùng gốc với issuer OIDC (https://auth.example/application/o/<slug>/), nên không
     * cần thêm biến cấu hình.
     *
     * @throws AuthentikUnavailable
     */
    private static function baseUrl(): string
    {
        $issuer = parse_url((string) config('services.authentik.issuer'));

        if (! is_array($issuer) || ! isset($issuer['scheme'], $issuer['host'])) {
            throw new AuthentikUnavailable('Chưa cấu hình AUTHENTIK_ISSUER.');
        }

        return $issuer['scheme'].'://'.$issuer['host'].(isset($issuer['port']) ? ':'.$issuer['port'] : '');
    }
}
