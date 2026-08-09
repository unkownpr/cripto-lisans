# Marka Kiti — Kripto Lisans Paneli

Slite referansından çıkarılan DNA. Kaynak: `public/assets/tokens.css` (tek doğruluk kaynağı — tüm renk/font/space token orada).

## Genre & ton

modern-minimal + playful sıcaklık. Güvenli, sade, sıcak. Bol beyaz alan, pastel vurgular, pill butonlar, doodle line-art.

## Renk

| Rol | Token | Değer (OKLCH) |
|---|---|---|
| Paper (bg) | `--color-paper` | sıcak krem off-white |
| Surface | `--color-paper-2` | near-white kart |
| Ink | `--color-ink` | sıcak siyah — başlık + primary buton |
| Body | `--color-ink-2` | metin |
| Muted | `--color-muted` | ikincil metin |
| **Accent** | `--color-accent` | canlı royal mavi — link, `.hl` vurgu, focus |
| Pastel | `--color-coral / --mint / --sky / --lilac` | stat/kart başlıkları |
| Dark | `--color-dark` | blob/footer band, `pre` |

**Kural:** primary buton = koyu ink pill (mavi değil). Mavi sadece link + vurgu + focus ring. Pastel yüzeyler sadece stat/hero kartlarında.

## Tipografi

- **Display**: Inter Tight (500–700), tight tracking (-0.02em), roman (italik başlık YOK)
- **Body**: Inter (400–600)
- **Mono**: JetBrains Mono — tokenId, adres, hash, kod

Ölçek `--text-xs … --text-display` tokens.css'te. Hero `.hl` = mavi altı çizili anahtar kelime (Slite imzası).

## Şekil & hareket

- Radius: `--radius-pill` (butonlar), `--radius-card` 16px (kartlar)
- Gölge: yumuşak sıcak `--shadow-sm/md/lg`
- 4pt space skalası `--space-2xs … --space-2xl`
- Easing: `--ease-out/in/in-out` (browser `ease` yasak), süre `--dur-fast/mid`
- `prefers-reduced-motion` desteklenir

## Bileşenler (CSS sınıfları)

`.btn` (ink pill) · `.btn-accent` (mavi) · `.btn-ghost` (outline) · `.btn-big`
`.card` · `.card--coral/--mint/--sky/--dark` · `.eyebrow` (pill etiket)
`.badge` · `.badge--ok/--warn/--muted` · `.stat-row/.stat` · `.hl` · `.addr` (mono chip)

## Taşınabilirlik

Yeni sayfa/proje: `tokens.css`'i kopyala, token'lara isimle referans ver (`var(--color-accent)`), asla ham OKLCH gömme. Marka değişimi = sadece tokens.css düzenle.
