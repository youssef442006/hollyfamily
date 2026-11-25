"""تحديد معدل المحاولات (rate limiting) بسيط في الذاكرة — لمنع تخمين كلمة المرور.
لا يتطلب Redis ولا مكتبة خارجية. مناسب لتطبيق عقدة واحدة (التوزيع الخارجي يحتاج Redis).
"""

import time
import threading
from collections import defaultdict, deque


class LoginRateLimiter:
    def __init__(self, max_attempts: int = 5, window_seconds: int = 900, lockout_seconds: int = 300):
        self.max_attempts = max_attempts
        self.window_seconds = window_seconds
        self.lockout_seconds = lockout_seconds
        self._failures: dict[str, deque] = defaultdict(deque)
        self._lockout_until: dict[str, float] = {}
        self._lock = threading.Lock()

    def _cleanup(self, key: str, now: float):
        dq = self._failures.get(key)
        if dq:
            while dq and now - dq[0] > self.window_seconds:
                dq.popleft()
            if not dq:
                self._failures.pop(key, None)

    def is_locked(self, key: str) -> bool:
        with self._lock:
            now = time.time()
            until = self._lockout_until.get(key, 0.0)
            if until > now:
                return True
            if until and until <= now:
                self._lockout_until.pop(key, None)
            self._cleanup(key, now)
            return len(self._failures.get(key, ())) >= self.max_attempts

    def record_failure(self, key: str):
        with self._lock:
            now = time.time()
            self._cleanup(key, now)
            dq = self._failures.setdefault(key, deque())
            dq.append(now)
            while len(dq) > self.max_attempts:
                dq.popleft()
            if len(dq) >= self.max_attempts:
                self._lockout_until[key] = now + self.lockout_seconds

    def reset(self, key: str):
        with self._lock:
            self._failures.pop(key, None)
            self._lockout_until.pop(key, None)


login_limiter = LoginRateLimiter()


def login_limit_key(request, username: str) -> str:
    client = request.client.host if request.client else "unknown"
    return f"{client}|{username.strip().lower()}"
