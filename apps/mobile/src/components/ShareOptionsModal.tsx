import { Modal, Pressable, Share, StyleSheet, Text, TouchableOpacity, View } from "react-native";
import * as Clipboard from "expo-clipboard";
import { SvgXml } from "react-native-svg";

import type { SharedLinkWithQr } from "../api/types";

interface ShareOptionsModalProps {
  visible: boolean;
  link: SharedLinkWithQr | null;
  onClose: () => void;
}

export default function ShareOptionsModal({ visible, link, onClose }: ShareOptionsModalProps) {
  if (!link) return null;

  async function handleShare() {
    await Share.share({ message: `${link!.label}: ${link!.url}`, url: link!.url });
  }

  async function handleCopyLink() {
    await Clipboard.setStringAsync(link!.url);
    onClose();
  }

  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <Pressable style={styles.backdrop} onPress={onClose}>
        <Pressable style={styles.sheet}>
          <View style={styles.handle} />
          <Text style={styles.title}>Share {link.label}</Text>
          <Text style={styles.expiry}>
            Link expires {new Date(link.expires_at).toLocaleDateString()}
          </Text>

          <View style={styles.qrBox}>
            <SvgXml xml={link.qr_code_svg} width={160} height={160} />
          </View>

          <TouchableOpacity style={styles.option} onPress={handleShare}>
            <Text style={styles.optionText}>Share via WhatsApp, Email, and more</Text>
          </TouchableOpacity>
          <TouchableOpacity style={styles.option} onPress={handleCopyLink}>
            <Text style={styles.optionText}>Copy link</Text>
          </TouchableOpacity>
        </Pressable>
      </Pressable>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: { flex: 1, backgroundColor: "rgba(0,0,0,0.4)", justifyContent: "flex-end" },
  sheet: {
    backgroundColor: "#f8fafc",
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    padding: 20,
    alignItems: "center",
    gap: 12,
  },
  handle: { width: 40, height: 4, borderRadius: 2, backgroundColor: "#ddd" },
  title: { fontSize: 18, fontWeight: "700", textAlign: "center" },
  expiry: { fontSize: 12, color: "#666" },
  qrBox: {
    width: 160,
    height: 160,
    borderRadius: 12,
    backgroundColor: "#fff",
    alignItems: "center",
    justifyContent: "center",
  },
  option: { backgroundColor: "#fff", borderRadius: 10, padding: 14, alignItems: "center", width: "100%" },
  optionText: { fontSize: 15, fontWeight: "600" },
});
