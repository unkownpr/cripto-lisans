# LicenseNFT — deploy

ERC-721 + EIP-712 lazy-mint license contract. Deploy with Foundry (or Remix).

## Foundry

```bash
# once
curl -L https://foundry.paradigm.xyz | bash && foundryup
cd contracts
forge init --force --no-commit
forge install OpenZeppelin/openzeppelin-contracts --no-commit
echo "@openzeppelin/=lib/openzeppelin-contracts/" > remappings.txt
```

Put `LicenseNFT.sol` under `src/`. Deploy to Sepolia:

```bash
export SEPOLIA_RPC=https://sepolia.infura.io/v3/YOUR_KEY
export PK=0xYOUR_ADMIN_PRIVATE_KEY          # the wallet that will sign vouchers
export ADMIN=0xYOUR_ADMIN_ADDRESS

forge create src/LicenseNFT.sol:LicenseNFT \
  --rpc-url $SEPOLIA_RPC --private-key $PK \
  --constructor-args $ADMIN
```

Copy the deployed address into the panel's `.env` → `CONTRACT=`.

## Remix (no toolchain)

1. Open https://remix.ethereum.org, paste `LicenseNFT.sol`.
2. Compiler 0.8.24, "Injected Provider - MetaMask" (network = Sepolia).
3. Deploy with constructor arg = your admin address.
4. Copy address → `.env`.

## Key invariant

The `admin` passed to the constructor gets `SIGNER_ROLE`. The panel's
`ADMIN_ADDRESSES` **must** be that same wallet — it signs the EIP-712 vouchers
the contract verifies in `redeem()`. Domain is fixed: `EIP712("LicensePanel","1")`.
