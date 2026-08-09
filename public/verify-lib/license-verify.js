/**
 * license-verify.js — PANEL-FREE lisans doğrulama.
 *
 * Ürün yazılımın bunu gömer. Panele HİÇ bağımlı değil — sadece zinciri okur
 * ve kullanıcının cüzdan sahipliğini kanıtlatır. Panel çökse de çalışır.
 *
 * Gerektirir: ethers v6 (CDN) + kullanıcının cüzdanı (window.ethereum).
 *
 *   import { verifyLicense } from './license-verify.js';
 *   const ok = await verifyLicense({
 *     rpcUrl:    'https://eth-sepolia.g.alchemy.com/v2/KEY', // okuma için (opsiyonel)
 *     contract:  '0xYourLicenseContract',
 *     productId: 7,          // BU ürünün on-chain id'si
 *     tokenId:   '123',      // kullanıcının lisans token'ı
 *   });
 *   if (ok) unlockApp();
 */

const ABI = [
  'function verifyLicense(uint256 tokenId, uint256 productId, address owner) view returns (bool)',
  'function ownerOf(uint256 tokenId) view returns (address)',
];

/**
 * @returns {Promise<boolean>} true → geçerli, bu ürün için, ve çağıran cüzdan sahibi.
 */
export async function verifyLicense({ contract, productId, tokenId, rpcUrl }) {
  if (!window.ethereum) throw new Error('Cüzdan (MetaMask) gerekli.');

  // 1) Cüzdanı bağla + sahiplik KANITI (product kendi nonce'unu üretir; panel yok)
  const browser = new ethers.BrowserProvider(window.ethereum);
  await browser.send('eth_requestAccounts', []);
  const signer = await browser.getSigner();
  const addr = await signer.getAddress();

  const nonce = (crypto.randomUUID?.() || String(Date.now() + Math.random()));
  const msg = `Lisans kontrolu\nurun: ${productId}\ntoken: ${tokenId}\nnonce: ${nonce}`;
  const sig = await signer.signMessage(msg);
  const recovered = ethers.verifyMessage(msg, sig);
  if (recovered.toLowerCase() !== addr.toLowerCase()) return false; // imza sahtesi

  // 2) Zinciri oku (panel yok). Kendi RPC'n varsa onu kullan; yoksa cüzdanın sağlayıcısı.
  const reader = rpcUrl ? new ethers.JsonRpcProvider(rpcUrl) : browser;
  const c = new ethers.Contract(contract, ABI, reader);

  // Tek çağrıda: valid + revoked-değil + productOf==productId + ownerOf==addr
  return await c.verifyLicense(tokenId, productId, addr);
}

/** Sadece zincir okuması — imza istemeden (zayıf; paylaşım engellemez). */
export async function isLicenseValid({ contract, productId, tokenId, owner, rpcUrl }) {
  const reader = rpcUrl
    ? new ethers.JsonRpcProvider(rpcUrl)
    : new ethers.BrowserProvider(window.ethereum);
  const c = new ethers.Contract(contract, ABI, reader);
  return await c.verifyLicense(tokenId, productId, owner);
}
