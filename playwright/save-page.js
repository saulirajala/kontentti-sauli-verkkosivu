import { chromium } from "playwright";
import fs from "fs";
import path from "path";

async function savePageWithRelativeAssets(url, outputDir) {
    const browser = await chromium.launch();
    const page = await browser.newPage();
    await page.goto(url, { waitUntil: "networkidle" });

    // Kerätään kaikki assetit (CSS/JS/kuvat)
    const urls = await page.$$eval("link[href], script[src], img[src]", elements => {
        const allUrls = [];
        elements.forEach(el => {
            if (el.href) allUrls.push(el.href);
            if (el.src) allUrls.push(el.src);

            // Jos elementissä on srcset, lisää kaikki siinä olevat kuvat
            if (el.srcset) {
                const srcsetUrls = el.srcset.split(",").map(s => s.trim().split(" ")[0]);
                console.log(el.srcset);
                allUrls.push(...srcsetUrls);
            }
        });
        return allUrls;
    });

    // Hae myös fonttien URLit <style class="wp-fonts-local">
    const fontUrls = await page.$$eval("style.wp-fonts-local", els => {
        const urls = [];
        els.forEach(el => {
            const matches = el.textContent.matchAll(/url\(['"]?(http:\/\/127\.0\.0\.1:9400\/[^'")]+)['"]?\)/g);
            for (const m of matches) urls.push(m[1]);
        });
        return urls;
    });

    const allUrls = [...urls, ...fontUrls];

    const skipUrls = [
        "/feed",
        "/comments/feed",
        "/wp-json",
        "/xmlrpc.php",
        "/?p=",
        "/oembed",
        "/etusivu/etusivu",
        "youtube.com",
        "youtu.be"
    ];

    console.log(allUrls);
    for (let assetUrl of allUrls) {
        if (!assetUrl.startsWith("http://127.0.0.1:9400/")) continue;
        // Skipataan tietyt URLit
        if (skipUrls.some(skip => assetUrl.includes(skip))) {
            continue;
        }

        try {
            const res = await fetch(assetUrl);
            const arrayBuffer = await res.arrayBuffer();
            const buffer = Buffer.from(arrayBuffer);

            const relPath = assetUrl.replace("http://127.0.0.1:9400/", "");
            const filePath = path.join(outputDir, relPath);

            fs.mkdirSync(path.dirname(filePath), { recursive: true });
            fs.writeFileSync(filePath, buffer);
            console.log(`Tallennettu: ${filePath}`);
        } catch (e) {
            console.log(`Virhe ladatessa ${assetUrl}: ${e}`);
        }
    }

    // Muokataan kaikki absoluuttiset polut relatiivisiksi
    await page.evaluate(() => {
        document.querySelectorAll("*").forEach(el => {
            // käsitellään src ja href
            ["src", "href"].forEach(attr => {
                if (el.hasAttribute(attr)) {
                    const val = el.getAttribute(attr);
                    if (val && val.startsWith("http://127.0.0.1:9400/")) {
                        const relPath = "." + val.replace("http://127.0.0.1:9400", "");
                        el.setAttribute(attr, relPath);
                    }
                }
            });

            // käsitellään srcset
            if (el.hasAttribute("srcset")) {
                const srcset = el.getAttribute("srcset");
                const newSrcset = srcset.split(",").map(s => {
                    const [url, size] = s.trim().split(" ");
                    let newUrl = url;
                    if (url.startsWith("http://127.0.0.1:9400/")) {
                        newUrl = "." + url.replace("http://127.0.0.1:9400", "");
                    }
                    return size ? `${newUrl} ${size}` : newUrl;
                }).join(", ");
                el.setAttribute("srcset", newSrcset);
            }
        });
        // Muokataan fonttien URLit relatiivisiksi <style class="wp-fonts-local">
        document.querySelectorAll("style.wp-fonts-local").forEach(styleEl => {
            styleEl.textContent = styleEl.textContent.replace(
                /url\(['"]?(http:\/\/127\.0\.0\.1:9400\/[^'")]+)['"]?\)/g,
                (match, p1) => `url(.${p1.replace('http://127.0.0.1:9400', '')})`
            );
        });
    });

    // Ota muokattu HTML talteen
    const html = await page.content();
    if (!fs.existsSync(outputDir)) fs.mkdirSync(outputDir, { recursive: true });
    fs.writeFileSync(path.join(outputDir, "index.html"), html, "utf8");

    await browser.close();
}

// Käyttö: node save-page-relative.js http://127.0.0.1:9400/ static-copy
const url = process.argv[2];
const outputDir = process.argv[3] || "static-copy";

savePageWithRelativeAssets(url, outputDir);
