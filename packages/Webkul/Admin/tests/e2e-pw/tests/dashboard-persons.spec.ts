import { expect, test } from "../fixtures/AdminFixtures";
import { csrfHeaders, runSuffix } from "../utils/acl";

test("opens a dashboard contact with a formatted phone number in the same tab", async ({ adminPage }) => {
    const suffix = runSuffix();
    const name = `Dashboard contact ${suffix}`;
    const email = `dashboard-${suffix}@example.test`;
    const phone = `+55 11 ${suffix}`;
    const personsUrl = new URL("/admin/contacts/persons", adminPage.url()).href;
    const headers = await csrfHeaders(adminPage.context());
    const created = await adminPage.request.post(`${personsUrl}/create`, {
        headers,
        form: {
            name,
            "emails[0][value]": email,
            "emails[0][label]": "work",
            "contact_numbers[0][value]": phone,
            "contact_numbers[0][label]": "work",
        },
    });
    expect(created.ok()).toBe(true);
    const { data: { id } } = await created.json();

    try {
        await adminPage.setViewportSize({ width: 393, height: 852 });
        await adminPage.route(
            url => url.pathname.endsWith("/dashboard/stats") && url.searchParams.get("type") === "top-persons",
            route => route.fulfill({ json: { statistics: [{ id, name, emails: [{ value: email }] }] } }),
        );

        await adminPage.goto(new URL("/admin/dashboard", personsUrl).href);
        await adminPage.getByRole("link").filter({ hasText: name }).click();

        await adminPage.waitForURL(`${personsUrl}/view/${id}`);
        await expect(adminPage.getByRole("heading", { name, exact: true })).toBeVisible();
        await expect(adminPage.getByText(phone).first()).toBeVisible();
    } finally {
        await adminPage.request.delete(`${personsUrl}/${id}`, {
            headers: await csrfHeaders(adminPage.context()),
        });
    }
});
