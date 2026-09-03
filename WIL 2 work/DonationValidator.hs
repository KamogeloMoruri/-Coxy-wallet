{-# LANGUAGE DataKinds           #-}
{-# LANGUAGE DeriveAnyClass      #-}
{-# LANGUAGE DeriveGeneric       #-}
{-# LANGUAGE NoImplicitPrelude   #-}
{-# LANGUAGE OverloadedStrings   #-}
{-# LANGUAGE ScopedTypeVariables #-}
{-# LANGUAGE TemplateHaskell     #-}
{-# LANGUAGE TypeApplications    #-}
{-# LANGUAGE TypeFamilies        #-}
{-# LANGUAGE TypeOperators       #-}

-- |
-- Module      : DonationValidator
-- Description : On-chain rules for the AI Donation Tracker's Cardano escrow
--
-- Off-chain code (backend/, cardano/offchain.js) can query balances and
-- assemble transactions, but none of that is *trustworthy* on its own —
-- anyone could send a malformed or dishonest transaction. This validator
-- is what actually enforces, at the ledger level, that donated funds can
-- only move in the ways the app intends:
--
--   * Release  — the declared beneficiary (the charity) may withdraw the
--                funds, but only for at least the promised amount, and
--                only before the pledge's deadline.
--   * Refund   — if the deadline passes and the charity never claims the
--                funds, the original donor may reclaim their own ADA.
--
-- This means the PHP backend's job is verification/bookkeeping (checking
-- what already happened and updating records), while THIS script is what
-- actually prevents funds from being taken by anyone else, regardless of
-- what the backend or frontend say.
module DonationValidator
  ( DonationDatum (..)
  , DonationAction (..)
  , donationValidator
  , donationScript
  , donationAddress
  , donationValidatorHash
  ) where

import           Ledger                       (Address, POSIXTime, PubKeyHash,
                                                 ScriptContext, Validator,
                                                 scriptContextTxInfo,
                                                 txInfoValidRange)
import qualified Ledger.Ada                    as Ada
import qualified Ledger.Address                as Address
import           Ledger.Interval               (contains, from, to)
import qualified Ledger.Typed.Scripts          as Scripts
import           Ledger.Value                  (Value)
import           Plutus.V1.Ledger.Contexts      (getContinuingOutputs,
                                                  txSignedBy, valuePaidTo)
import qualified PlutusTx
import           PlutusTx.Prelude
import qualified Prelude                       as Haskell

-- ---------------------------------------------------------------------
-- Datum: the terms of one pledged donation, locked at the script address
-- ---------------------------------------------------------------------

data DonationDatum = DonationDatum
  { ddDonor          :: PubKeyHash   -- ^ who may reclaim funds after the deadline
  , ddBeneficiary    :: PubKeyHash   -- ^ the charity allowed to withdraw funds
  , ddPurpose        :: BuiltinByteString -- ^ e.g. "Food", "Education" — matches the app's categories
  , ddExpectedAmount :: Integer      -- ^ minimum lovelace the beneficiary must actually receive
  , ddDeadline       :: POSIXTime    -- ^ after this time, an unclaimed donation can be refunded
  } deriving Haskell.Show

PlutusTx.unstableMakeIsData ''DonationDatum
PlutusTx.makeLift ''DonationDatum

-- ---------------------------------------------------------------------
-- Redeemer: which action the transaction is attempting
-- ---------------------------------------------------------------------

data DonationAction = Release | Refund
  deriving Haskell.Show

PlutusTx.unstableMakeIsData ''DonationAction
PlutusTx.makeLift ''DonationAction

-- ---------------------------------------------------------------------
-- Validator logic
-- ---------------------------------------------------------------------

{-# INLINABLE mkDonationValidator #-}
mkDonationValidator :: DonationDatum -> DonationAction -> ScriptContext -> Bool
mkDonationValidator datum action ctx =
  case action of
    Release ->
      traceIfFalse "beneficiary signature missing"       signedByBeneficiary
        && traceIfFalse "payment before the deadline required" beforeDeadline
        && traceIfFalse "beneficiary was not paid the expected amount" beneficiaryPaidEnough

    Refund ->
      traceIfFalse "donor signature missing"              signedByDonor
        && traceIfFalse "refund only allowed after the deadline" afterDeadline
        && traceIfFalse "donor was not refunded the full amount" donorPaidEnough
  where
    info = scriptContextTxInfo ctx

    signedByBeneficiary = txSignedBy info (ddBeneficiary datum)
    signedByDonor        = txSignedBy info (ddDonor datum)

    -- txInfoValidRange must fall entirely within the required window; a
    -- transaction can't claim "before the deadline" by fudging its slot
    -- range, since the ledger itself enforces the range is honest.
    beforeDeadline = to (ddDeadline datum) `contains` txInfoValidRange info
    afterDeadline  = from (ddDeadline datum) `contains` txInfoValidRange info

    paidToBeneficiary :: Value
    paidToBeneficiary = valuePaidTo info (ddBeneficiary datum)

    paidToDonor :: Value
    paidToDonor = valuePaidTo info (ddDonor datum)

    beneficiaryPaidEnough =
      Ada.fromValue paidToBeneficiary >= Ada.lovelaceOf (ddExpectedAmount datum)

    donorPaidEnough =
      Ada.fromValue paidToDonor >= Ada.lovelaceOf (ddExpectedAmount datum)

-- ---------------------------------------------------------------------
-- Boilerplate: typed validator, compiled script, address
-- ---------------------------------------------------------------------

data Donating
instance Scripts.ValidatorTypes Donating where
  type instance DatumType Donating    = DonationDatum
  type instance RedeemerType Donating = DonationAction

typedDonationValidator :: Scripts.TypedValidator Donating
typedDonationValidator =
  Scripts.mkTypedValidator @Donating
    $$(PlutusTx.compile [|| mkDonationValidator ||])
    $$(PlutusTx.compile [|| wrap ||])
  where
    wrap = Scripts.wrapValidator @DonationDatum @DonationAction

donationValidator :: Validator
donationValidator = Scripts.validatorScript typedDonationValidator

donationScript :: Scripts.TypedValidator Donating
donationScript = typedDonationValidator

donationValidatorHash :: Scripts.ValidatorHash
donationValidatorHash = Scripts.validatorHash typedDonationValidator

donationAddress :: Address
donationAddress = Scripts.validatorAddress typedDonationValidator
