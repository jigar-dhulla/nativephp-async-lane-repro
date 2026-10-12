# NativePHP Mobile: Android AsyncTask fails when dispatched at app launch

Minimal repro for NativePHP Mobile 4.x on Android. An `AsyncTask` dispatched from a `NativeComponent`'s `mount()` on the first screen is refused with `Native\Mobile\Exceptions\AsyncTaskException: The async task could not be started — the background lane is not available.` The same dispatch succeeds a few seconds later (the "Dispatch again" button). The task is trivial and needs no network. In logcat the dispatch is dropped with "no async executor running", and the executor only starts about 2.4 s later, together with the deferred queue worker.

## Environment

| | |
|---|---|
| nativephp/mobile | 4.6.0 |
| nativephp/mobile-ui | 0.8.0 (registered in `NativeServiceProvider`; without it the screen renders blank) |
| Laravel | 13.35.0 |
| PHP (host) | 8.4.25 (the app bundle runs PHP 8.4.26) |
| Android | emulator `sdk_gphone64_arm64`, API level 36, debug build |
| Host OS | macOS 26.6.2 (Apple silicon) |
| iOS | not checked |

## Steps to reproduce

```bash
composer install
cp .env.example .env
php artisan key:generate
# .env.example already sets NATIVEPHP_APP_ID=com.example.asyncrepro

export JAVA_HOME="/Applications/Android Studio.app/Contents/jbr/Contents/Home" ANDROID_HOME=$HOME/Library/Android/sdk PATH=$ANDROID_HOME/platform-tools:$PATH
php artisan native:install android
php artisan native:run android <emulator-id> --no-tty --no-interaction

# cold start, repeat as often as you like
adb shell am force-stop com.example.asyncrepro
adb shell monkey -p com.example.asyncrepro -c android.intent.category.LAUNCHER 1
sleep 15
adb exec-out screencap -p > shot.png
adb shell input tap 540 1396   # "Dispatch again" on a 1080x2400 screen
```

Note: after editing the app, uninstall it (`adb uninstall com.example.asyncrepro`) before `native:run`; with an unchanged version id the device kept the old bundle ("Laravel already up to date") and showed stale screens.

The screen is `app/NativeComponents/Repro.php` (dispatch in `mount()`) with `app/Async/PingTask.php`.

## Expected vs actual

- Expected: a task dispatched during the first screen's `mount()` is queued until the background lane is ready (or there is a documented ready hook), then `finished()` fires with `ok`.
- Actual: `failed()` fires within about 10 to 40 ms of mount with `AsyncTaskException: The async task could not be started — the background lane is not available.` Apps that treat a failed task as a network error show "offline".

## Result

Cold starts that showed FAILED for the in-`mount()` dispatch: **3 of 3**. Each run's first-screen text and the later success:

- `evidence/cold-start-1.png`, `cold-start-2.png`, `cold-start-3.png`: FAILED line after each cold start (callback after 8 to 41 ms).
- `evidence/after-dispatch-again.png`: "Dispatch again" pressed about 14 s after launch gives `RESULT: ok ...`.
- `evidence/logcat-excerpt.txt`: filtered logcat of the app process for the three cold starts. Per run, the dispatch is refused and the executor starts later:

```
23:05:48.924 W AsyncTask.Dispatch: no async executor running; task ... dropped
23:05:51.385 D MainActivity: Starting deferred background queue worker
23:05:51.394 I AsyncTaskExecutor: Async executor started (4 threads)
23:05:51.518 I PHP-Native: worker_boot: worker PHP interpreter ready
23:05:51.518 I PHPQueueWorker: Worker runtime booted, processing jobs
```

Gap between the refused dispatch and the executor start: about 2.5 s in all three runs.

## Where this comes from in the vendor code

- PHP: `nativephp/mobile/src/PendingAsyncTask.php`, `start()`: when `AsyncTaskTransport::dispatch(...)` returns false it calls `failLocally('The async task could not be started — the background lane is not available.', ...)`, which invokes `failed()` with an `AsyncTaskException`.
- Android: `resources/androidstudio/app/src/main/java/com/nativephp/mobile/bridge/AsyncTaskExecutor.kt`: dispatching while the executor is not running is refused (it returns false, so the bridge answers `{"success":false,"error":"executor not running"}`) until the executor is started, which happens in `MainActivity` as part of "Starting deferred background queue worker" after the first screen is already published.

I did not read or test anything beyond this on the Android side.

## Suggested fixes

1. Queue tasks dispatched before the executor is running and run them once it starts (preferred, no app change needed).
2. Or fire an event or hook when the background lane is ready, so apps can dispatch from it instead of from `mount()`.
3. Or document that tasks dispatched from the first screen's `mount()` may be refused on Android, and how to retry (for example retry once on `failed()` after a short delay).

## iOS

Not checked. This repo was only run on an Android emulator.
