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
