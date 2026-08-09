// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

import "@openzeppelin/contracts/token/ERC721/extensions/ERC721URIStorage.sol";
import "@openzeppelin/contracts/utils/cryptography/EIP712.sol";
import "@openzeppelin/contracts/utils/cryptography/ECDSA.sol";
import "@openzeppelin/contracts/access/AccessControl.sol";

/**
 * @title LicenseNFT
 * @notice Software/digital-product licenses as ERC-721 NFTs with EIP-712
 *         lazy minting. Admin signs a Voucher off-chain (no gas); the buyer
 *         redeems it into their own wallet and pays the mint gas.
 *
 *  - expiryOf[tokenId] == 0  -> perpetual license
 *  - expiryOf[tokenId]  > 0  -> valid until that unix timestamp
 *  - revoked[tokenId]        -> hard kill switch (admin only)
 *
 * Transfer = standard ERC-721 transferFrom (the "license devir" flow).
 */
contract LicenseNFT is ERC721URIStorage, EIP712, AccessControl {
    using ECDSA for bytes32;

    bytes32 public constant SIGNER_ROLE = keccak256("SIGNER_ROLE");

    mapping(uint256 => uint64) public expiryOf; // 0 = perpetual
    mapping(uint256 => bool) public revoked;
    mapping(uint256 => uint256) public productOf; // tokenId -> productId (panel-free product binding)

    struct Voucher {
        uint256 tokenId;
        uint256 productId; // which product this license unlocks
        address recipient; // address(0) = claimable by anyone
        uint64 expiry; // 0 = perpetual
        string uri; // token metadata URI
        uint256 price; // wei required to redeem (0 = free)
    }

    bytes32 private constant VOUCHER_TYPEHASH = keccak256(
        "Voucher(uint256 tokenId,uint256 productId,address recipient,uint64 expiry,string uri,uint256 price)"
    );

    event Redeemed(uint256 indexed tokenId, uint256 indexed productId, address indexed to, uint64 expiry);
    event Revoked(uint256 indexed tokenId);

    constructor(address admin)
        ERC721("License", "LIC")
        EIP712("LicensePanel", "1")
    {
        _grantRole(DEFAULT_ADMIN_ROLE, admin);
        _grantRole(SIGNER_ROLE, admin);
    }

    /// @notice Redeem an admin-signed voucher; mints to msg.sender.
    function redeem(Voucher calldata v, bytes calldata sig) external payable {
        address signer = _hash(v).recover(sig);
        require(hasRole(SIGNER_ROLE, signer), "invalid signer");
        require(
            v.recipient == address(0) || v.recipient == msg.sender,
            "not intended recipient"
        );
        require(msg.value >= v.price, "underpaid");

        _safeMint(msg.sender, v.tokenId);
        _setTokenURI(v.tokenId, v.uri);
        expiryOf[v.tokenId] = v.expiry;
        productOf[v.tokenId] = v.productId;

        emit Redeemed(v.tokenId, v.productId, msg.sender, v.expiry);
    }

    /// @notice Single source of truth for the /api/verify endpoint.
    function isValid(uint256 tokenId) external view returns (bool) {
        if (_ownerOf(tokenId) == address(0)) return false;
        if (revoked[tokenId]) return false;
        uint64 e = expiryOf[tokenId];
        return e == 0 || e > block.timestamp;
    }

    /// @notice Panel-free gate: true iff token is valid, owned by `owner`, and for `productId`.
    function verifyLicense(uint256 tokenId, uint256 productId, address owner)
        external
        view
        returns (bool)
    {
        return _ownerOf(tokenId) == owner
            && !revoked[tokenId]
            && productOf[tokenId] == productId
            && (expiryOf[tokenId] == 0 || expiryOf[tokenId] > block.timestamp);
    }

    function revoke(uint256 tokenId) external onlyRole(DEFAULT_ADMIN_ROLE) {
        revoked[tokenId] = true;
        emit Revoked(tokenId);
    }

    /// @notice Phase 2 — crypto checkout: buyers pay `price` on redeem; admin pulls proceeds.
    function withdraw(address payable to) external onlyRole(DEFAULT_ADMIN_ROLE) {
        uint256 bal = address(this).balance;
        (bool ok, ) = to.call{value: bal}("");
        require(ok, "withdraw failed");
    }

    function _hash(Voucher calldata v) internal view returns (bytes32) {
        return _hashTypedDataV4(
            keccak256(
                abi.encode(
                    VOUCHER_TYPEHASH,
                    v.tokenId,
                    v.productId,
                    v.recipient,
                    v.expiry,
                    keccak256(bytes(v.uri)),
                    v.price
                )
            )
        );
    }

    function supportsInterface(bytes4 interfaceId)
        public
        view
        override(ERC721URIStorage, AccessControl)
        returns (bool)
    {
        return super.supportsInterface(interfaceId);
    }
}
