<?php
require_once 'config.php';
checkAuth();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['product_id'])) {
    echo json_encode(['success' => false, 'message' => 'Requête invalide']);
    exit;
}

try {
    $productId = (int)$_POST['product_id'];
    $productName = trim($_POST['product_name'] ?? '');
    $reference = trim($_POST['reference'] ?? '');
    $currentQuantity = (int)($_POST['quantity'] ?? 0);
    $threshold = (int)($_POST['threshold'] ?? 0);

    $stmt = $db->prepare("
        SELECT p.id, p.nom, p.reference, p.seuil_alerte, COALESCE(s.quantite, 0) AS quantite,
               f.id AS fournisseur_id, f.nom AS fournisseur_nom, f.email, f.telephone
        FROM produits p
        LEFT JOIN stock s ON s.produit_id = p.id
        LEFT JOIN fournisseurs f ON p.fournisseur_id = f.id
        WHERE p.id = ?
    ");
    $stmt->execute([$productId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new Exception('Produit introuvable');
    }

    if ($productName === '') {
        $productName = $row['nom'];
    }
    if ($reference === '') {
        $reference = $row['reference'];
    }
    if ($threshold <= 0) {
        $threshold = (int)$row['seuil_alerte'];
    }
    if (!isset($_POST['quantity'])) {
        $currentQuantity = (int)$row['quantite'];
    }

    $isOut = $currentQuantity <= 0;
    $statusLabel = $isOut ? 'RUPTURE DE STOCK' : 'STOCK FAIBLE';
    $quantityToOrder = max($threshold * 2 - $currentQuantity, 1);

    $to = $row['email'] ?: ADMIN_EMAIL;
    $supplierName = $row['fournisseur_nom'] ?: 'Fournisseur';

    $subject = ($isOut ? '[URGENT] Rupture' : '[ALERTE] Stock faible') . ' — ' . $productName;

    $message = "
    <html><body style='font-family:Arial,sans-serif;line-height:1.5;color:#222'>
      <div style='background:#0f3460;color:#fff;padding:16px;text-align:center'>
        <h2 style='margin:0'>" . htmlspecialchars(COMPANY_NAME) . "</h2>
        <p style='margin:6px 0 0'>" . htmlspecialchars($statusLabel) . "</p>
      </div>
      <div style='padding:20px'>
        <p>Bonjour " . htmlspecialchars($supplierName) . ",</p>
        <p>Nous vous contactons concernant une <strong>alerte de stock</strong> :</p>
        <table style='width:100%;border-collapse:collapse;margin:16px 0'>
          <tr style='background:#f2f2f2'>
            <th style='border:1px solid #ddd;padding:8px;text-align:left'>Produit</th>
            <th style='border:1px solid #ddd;padding:8px;text-align:left'>Référence</th>
            <th style='border:1px solid #ddd;padding:8px;text-align:left'>Stock</th>
            <th style='border:1px solid #ddd;padding:8px;text-align:left'>Seuil</th>
            <th style='border:1px solid #ddd;padding:8px;text-align:left'>À commander</th>
          </tr>
          <tr>
            <td style='border:1px solid #ddd;padding:8px'>" . htmlspecialchars($productName) . "</td>
            <td style='border:1px solid #ddd;padding:8px'>" . htmlspecialchars($reference) . "</td>
            <td style='border:1px solid #ddd;padding:8px;color:" . ($isOut ? '#c00' : '#b36b00') . ";font-weight:bold'>" . $currentQuantity . "</td>
            <td style='border:1px solid #ddd;padding:8px'>" . $threshold . "</td>
            <td style='border:1px solid #ddd;padding:8px'><strong>" . $quantityToOrder . "</strong></td>
          </tr>
        </table>
        <p>Merci de confirmer la disponibilité et le délai de livraison.</p>
        <p>Contact : " . htmlspecialchars(PHONE_NUMBER) . "</p>
        <p>Cordialement,<br>L'équipe " . htmlspecialchars(COMPANY_NAME) . "</p>
      </div>
    </body></html>";

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . EMAIL_FROM . "\r\n";
    $headers .= "Reply-To: " . EMAIL_REPLY_TO . "\r\n";

    $mailOk = @mail($to, $subject, $message, $headers);

    // Log email
    try {
        $log = $db->prepare("
            INSERT INTO log_email_operations (produit_id, fournisseur_id, email, sujet, message, statut, erreur)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $fid = $row['fournisseur_id'] ?: 0;
        $log->execute([
            $productId,
            $fid,
            $to,
            $subject,
            strip_tags($message),
            $mailOk ? 'envoye' : 'erreur',
            $mailOk ? null : 'mail() a échoué (environnement local possible)'
        ]);
    } catch (Exception $e) {
        // ignore log errors
    }

    // Notification admin
    try {
        ensure_notifications_table($db);
        $db->prepare("
            INSERT INTO notifications (type, titre, message, produit_id, niveau, destinataire_role)
            VALUES ('commande_email', ?, ?, ?, ?, 'admin')
        ")->execute([
            'Email alerte: ' . $productName,
            'Email ' . ($mailOk ? 'envoyé' : 'préparé') . ' à ' . $to . ' — ' . $statusLabel . ' (qty ' . $currentQuantity . ')',
            $productId,
            $isOut ? 'danger' : 'warning'
        ]);
    } catch (Exception $e) {}

    // Aussi email au super admin en copie logique (si différent)
    if (ADMIN_EMAIL && strcasecmp(ADMIN_EMAIL, $to) !== 0) {
        @mail(ADMIN_EMAIL, '[Admin] ' . $subject, $message, $headers);
    }

    if (!$mailOk) {
        // En local, on ouvre aussi un mailto côté client via fallback
        $mailto = 'mailto:' . rawurlencode($to)
            . '?subject=' . rawurlencode($subject)
            . '&body=' . rawurlencode(
                "Bonjour $supplierName,\n\n"
                . "$statusLabel\n"
                . "Produit: $productName\n"
                . "Référence: $reference\n"
                . "Stock actuel: $currentQuantity\n"
                . "Seuil: $threshold\n"
                . "Quantité à commander: $quantityToOrder\n\n"
                . "Merci de confirmer disponibilité.\n\n"
                . "Cordialement,\n" . COMPANY_NAME
            );

        echo json_encode([
            'success' => true,
            'message' => "Email serveur indisponible — ouverture du client mail.",
            'mailto' => $mailto,
            'fallback' => true
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Email d\'alerte envoyé à ' . $to
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
