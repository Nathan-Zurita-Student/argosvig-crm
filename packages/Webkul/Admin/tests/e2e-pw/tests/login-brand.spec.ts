import { expect, test } from "@playwright/test";

test("shows the login form on mobile and toggles password visibility", async ({ page }) => {
    await page.setViewportSize({ width: 393, height: 852 });
    await page.goto("admin/login");
    const email = page.locator("#email");
    const password = page.locator("#password");
    const visibility = page.locator("#visibilityIcon");

    await expect(email).toHaveAttribute("autocomplete", "username");
    await expect(password).toHaveAttribute("autocomplete", "current-password");
    await expect(page.locator(".sign-in-identity svg, .sign-in-identity img")).toBeVisible();
    await expect(page.locator(".sign-in-submit")).toBeInViewport();

    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBe(393);

    await password.fill("test-password");
    await visibility.focus();
    await page.keyboard.press("Space");
    await expect(visibility).toHaveAttribute("aria-pressed", "true");
    await expect(password).toHaveAttribute("type", "text");

    await page.keyboard.press("Space");
    await expect(password).toHaveAttribute("type", "password");
    await expect(password).toHaveValue("test-password");

    await page.locator(".sign-in-password-label a").click();
    await expect(page).toHaveURL(/\/admin\/forget-password$/);
});

test("animates the shield rings and honors reduced motion", async ({ page }) => {
    await page.emulateMedia({ reducedMotion: "no-preference" });
    await page.goto("admin/login");
    const tracer = page.locator(".sign-in-orbit-tracer");
    await expect(tracer).toHaveCSS("animation-name", "sign-in-rotation");

    const transform = await tracer.evaluate(element => getComputedStyle(element).transform);
    await expect.poll(() => tracer.evaluate(element => getComputedStyle(element).transform)).not.toBe(transform);

    await page.emulateMedia({ reducedMotion: "reduce" });
    await expect(tracer).toHaveCSS("animation-name", "none");
    await expect(page.locator(".sign-in-emblem > svg")).toHaveCSS("animation-name", "none");
});

test("moves from email to password and only validates on a sign-in attempt", async ({ page }) => {
    let submitted = 0;
    await page.route("**/admin/login", route => {
        if (route.request().method() === "POST") {
            submitted += 1;

            return route.fulfill({ contentType: "text/html", body: "<title>Login submitted</title>" });
        }

        return route.continue();
    });

    await page.goto("admin/login");
    const email = page.locator("#email");
    const password = page.locator("#password");
    const emailError = page.locator("#email-error");
    const passwordError = page.locator("#password-error");

    await email.pressSequentially("usuario");
    await email.press("Enter");
    await expect(password).toBeFocused();
    await password.pressSequentially("123");
    await password.press("Tab");
    await expect(emailError).toHaveCount(0);
    await expect(passwordError).toHaveCount(0);
    expect(submitted).toBe(0);

    await page.locator(".sign-in-submit").click();
    await expect(emailError).toBeVisible();
    await expect(passwordError).toBeVisible();
    expect(submitted).toBe(0);

    await email.fill("usuario@example.test");
    await email.press("Enter");
    await expect(password).toBeFocused();
    await password.fill("password-test");

    const attempt = page.waitForRequest(request => request.method() === "POST" && request.url().endsWith("/admin/login"));
    await password.press("Enter");
    const body = new URLSearchParams((await attempt).postData() ?? "");
    expect(body.get("email")).toBe("usuario@example.test");
    expect(body.get("password")).toBe("password-test");
});
